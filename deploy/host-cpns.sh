#!/usr/bin/env bash
#
# Publishes the SUPA CPNS Laravel app as https://cpns.buatjalan.xyz/ through the
# system Caddy on this host and its own PHP-FPM pool, and verifies it over the
# public URL it is actually reached at.
#
# The app is no longer static files: Caddy terminates TLS, hands PHP to the
# pool's socket and serves public/ for everything else, so publishing a change is
# a front-end build, the module's database migrations, and a pool reload. What is
# left to get wrong is the same as for a static deploy, with one more failure mode
# on top of it — the Caddyfile here serves eleven other sites, and a reload that
# fails takes them with it. So:
#
#   * the new site block is smoke-tested on a spare loopback port, in front of the
#     real pool, before the system Caddy is asked to load it — including fetching
#     every asset, stylesheet and built file the page references;
#   * the Caddyfile edit is a block replacement (never an append), backed up
#     first, validated before the reload, and the rollback block is kept in the
#     repository so `disable` is one command;
#   * the result is verified over https, byte for byte against what the smoke test
#     rendered, with the neighbours re-checked after the reload rather than
#     assumed.
#
# Usage: deploy/host-cpns.sh <build|migrate|pool|smoke|enable|disable|verify|status>
set -euo pipefail

HOST=cpns.buatjalan.xyz
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PUBLIC_ROOT="$PROJECT_ROOT/public"
SNIPPET="$PROJECT_ROOT/deploy/caddy/$HOST.caddy"
ROLLBACK_SNIPPET="$PROJECT_ROOT/deploy/caddy/$HOST.static-clone.caddy"
POOL_SOURCE="$PROJECT_ROOT/deploy/php/php8.3-fpm-supa-cpns.conf"
POOL_TARGET=/etc/php/8.3/fpm/pool.d/supa-cpns.conf
SOCKET=/run/php/php8.3-fpm-supa-cpns.sock
CADDYFILE=/etc/caddy/Caddyfile
ACCESS_LOG=/var/log/caddy/cpns-access.log
STATE_DIR="$PROJECT_ROOT/storage/app/deploy"
STAGE_PORT="${STAGE_PORT:-8099}"
FPM_SERVICE=php8.3-fpm
URL="https://$HOST"

# Every other site this Caddyfile serves, re-read after the reload rather than
# assumed: killing a neighbour's config is the failure the backup/validate dance
# exists to prevent.
NEIGHBOURS=(lam-router.buatjalan.xyz lam-cyberlab.buatjalan.xyz dummy.buatjalan.xyz
  sinatria.buatjalan.xyz bring.buatjalan.xyz media.buatjalan.xyz ciora.buatjalan.xyz
  portfolio.buatjalan.xyz echo.buatjalan.xyz robin.buatjalan.xyz ppt.buatjalan.xyz)

# This script runs under sudo, where the secure PATH selects the system Node (v18
# on this host) while the build needs the project owner's Node (nvm v24): the
# toolchain in devDependencies is ESM-only and Node 18 cannot require it. Take the
# owner's newest nvm Node and put it first for the rest of the run.
if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != "root" ]]; then
  owner_bin="$(ls -d "$(getent passwd "$SUDO_USER" | cut -d: -f6)"/.nvm/versions/node/*/bin 2>/dev/null | sort -V | tail -1 || true)"
  if [[ -n "$owner_bin" ]]; then PATH="$owner_bin:$PATH"; fi
fi
export PATH

# Steps that touch the application run as its owner rather than as root: build
# output and compiled views stay owned by the account that will rewrite them, and
# `-H` restores the owner's HOME so a browser step finds the playwright browser in
# the owner's cache instead of /root/.cache. PATH is re-exported inside because
# sudo's env_reset would otherwise put the system Node back in front.
as_owner() {
  if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != "root" ]]; then
    sudo -u "$SUDO_USER" -H bash -c "export PATH=$(printf '%q' "$PATH"); $1"
  else
    bash -c "$1"
  fi
}

pass_count=0
fail_count=0

ok() { printf 'ok   %s\n' "$*"; pass_count=$((pass_count + 1)); }
bad() { printf 'FAIL %s\n' "$*"; fail_count=$((fail_count + 1)); }
step() { printf '\n== %s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }
need_root() { [[ $EUID -eq 0 ]] || die "this step edits system configuration — run it as root: sudo bash deploy/host-cpns.sh $1"; }

# One scratch directory for the whole run, cleaned by a single EXIT trap: a RETURN
# trap set inside a check function stays installed afterwards and then fires again
# for every later function, with its locals already gone.
scratch=""
stage_pid=""
ensure_scratch() {
  if [[ -z "$scratch" ]]; then
    scratch="$(mktemp -d)"
  fi
}
cleanup() {
  if [[ -n "$stage_pid" ]] && kill -0 "$stage_pid" 2>/dev/null; then
    kill "$stage_pid" 2>/dev/null || true
    wait "$stage_pid" 2>/dev/null || true
  fi
  [[ -n "$scratch" ]] && rm -rf "$scratch"
}
trap cleanup EXIT

usage() {
  printf 'usage: %s <build|migrate|pool|smoke|enable|disable|verify|status>\n\n' "${0##*/}"
  printf '  build    npm run build + artisan migrate --force + artisan optimize (as the owner)\n'
  printf '  migrate  php artisan migrate --force, as the project owner (also run by build)\n'
  printf '  pool     install/refresh the PHP-FPM pool file and reload php8.3-fpm\n'
  printf '  smoke    run the new site block on 127.0.0.1:%s against the real pool\n' "$STAGE_PORT"
  printf '  enable   build (incl. migrations), pool, smoke, replace the Caddyfile block, reload, verify\n'
  printf '  disable  put the previous (static clone) block back and reload\n'
  printf '  verify   fetch the live URL, compare it with the smoke render, walk its assets\n'
  printf '  status   services, pool, which block is live, certificate\n'
}

# The staged listener is on 127.0.0.1:$STAGE_PORT while the app writes absolute
# URLs from the request it is answering (Laravel builds them from the request
# host, not from APP_URL), so the origin is normalised away before the smoke
# render and the live page are compared. Everything else must match byte for
# byte: this is what proves the public URL serves the artifact that was checked.
normalize_origin() {
  sed -E "s#(https?)://(127\.0\.0\.1:$STAGE_PORT|$HOST)#ORIGIN#g" "$1" | sha256sum | awk '{print $1}'
}

# Fetch every asset-like path the rendered page references and report the ones
# that do not answer 200. Paths without a file extension are links to other pages
# — some of them deliberately outside this clone — so they are listed, not failed.
check_references() {
  local base="$1" html="$2" status path checked=0 broken=0 links=0
  local -a urls=()

  mapfile -t urls < <(php "$PROJECT_ROOT/deploy/reference-urls.php" --base="$base" < "$html")

  for path in "${urls[@]}"; do
    case "${path%%\?*}" in
      *.*) ;;
      *) links=$((links + 1)); continue ;;
    esac

    status="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$base$path" || true)"
    checked=$((checked + 1))

    if [[ "$status" != "200" ]]; then
      broken=$((broken + 1))
      [[ $broken -le 8 ]] && bad "reference $status $path"
    fi
  done

  if [[ $broken -eq 0 ]]; then
    ok "every referenced asset answers 200 ($checked files, $links page links skipped)"
  else
    bad "$broken of $checked referenced assets do not answer 200"
  fi
}

# Replace the hostname's site block in a Caddyfile with a snippet, in place.
# A Caddyfile block is matched by its label line and closed by the first line
# consisting only of "}"; the placeholder braces inside handler arguments
# ({hostport}, {path}) never sit alone on a line, so the count stays honest.
replace_block() {
  local file="$1" snippet="$2"
  python3 - "$file" "$snippet" "$HOST" <<'PY'
import sys
from pathlib import Path

target, snippet_path, host = sys.argv[1], sys.argv[2], sys.argv[3]
lines = Path(target).read_text().splitlines()
snippet = Path(snippet_path).read_text().rstrip('\n') + '\n'

start = next(
    (i for i, line in enumerate(lines)
     if line.strip().startswith(host) and line.rstrip().endswith('{')),
    None,
)
if start is None:
    sys.exit(f'no site block for {host} in {target}')

depth = 0
end = None
for i in range(start, len(lines)):
    stripped = lines[i].strip()
    if stripped.endswith('{'):
        depth += 1
    if stripped == '}':
        depth -= 1
        if depth == 0:
            end = i
            break
if end is None:
    sys.exit(f'unbalanced braces in the {host} block of {target}')

Path(target).write_text('\n'.join(lines[:start] + snippet.splitlines() + lines[end + 1:]) + '\n')
print(f'replaced {host} block: lines {start + 1}-{end + 1} -> {len(snippet.splitlines())} lines')
PY
}

reload_caddy() {
  caddy validate --config "$CADDYFILE" --adapter caddyfile >/dev/null || die 'the edited Caddyfile does not validate — nothing was reloaded'
  systemctl reload caddy
  sleep 1
  systemctl is-active --quiet caddy || die 'caddy is not active after the reload'
}

# The module's migrations are part of the deploy, not a step the operator is
# expected to remember: the smoke test only fetches `/` (which renders from
# resources/content and touches no table), so a deploy that skipped this would
# print "0 failed" while every page that reads the database — /masuk, /daftar,
# /tryout, /admin — answered 500 on a missing table, and the documented
# `php artisan cpns:admin` could not create the first administrator either.
#
# Run as the project owner, like the node steps above: the SQLite file and the
# schema inside it have to end up owned by the account that will write to them,
# and running this as root would leave a root-owned database the pool cannot
# write. `--force` because production is also the environment `enable` runs in.
# Safe to repeat: `migrate` is a no-op once the schema is current, so a second
# `enable` applies nothing.
cmd_migrate() {
  step 'migrate: apply any pending database migrations, as the project owner'
  local output

  if ! output="$(as_owner "cd $(printf '%q' "$PROJECT_ROOT") && php artisan migrate --force" 2>&1)"; then
    printf '%s\n' "$output" >&2
    die 'the database migration failed — the deploy stops here, before the smoke test and before the live config is touched'
  fi

  printf '%s\n' "$output" | sed 's/^/  /'
  ok 'database migrations applied (re-running applies nothing once the schema is current)'
}

# How many migrations are still waiting, for `status`. Printed rather than
# assumed, for the same reason the host's APP_LOCALE is: only the host's own
# repository and database answer it.
pending_migrations() {
  local output
  output="$(as_owner "cd $(printf '%q' "$PROJECT_ROOT") && php artisan migrate:status --pending" 2>/dev/null || true)"
  # The command prints a table header even with nothing pending, so count the
  # data rows rather than the lines.
  printf '%s\n' "$output" | grep -cE '^\s*\|?\s*[0-9]{4}_[0-9]{2}_[0-9]{2}' || true
}

cmd_build() {
  step 'build: front-end bundle, database schema and framework caches'
  as_owner "cd $(printf '%q' "$PROJECT_ROOT") && npm run build" >/dev/null
  [[ -f "$PUBLIC_ROOT/build/manifest.json" ]] || die 'no build manifest after npm run build'
  ok "front-end bundle built ($(ls -1 "$PUBLIC_ROOT/build/assets" | wc -l) files in public/build/assets)"

  # Before the caches, so the schema the cached config points at exists.
  cmd_migrate

  as_owner "cd $(printf '%q' "$PROJECT_ROOT") && php artisan optimize" >/dev/null
  ok 'artisan optimize (config, events, routes, views cached)'
}

cmd_pool() {
  need_root pool
  step 'pool: install the PHP-FPM pool and reload it'
  [[ -f "$POOL_TARGET" ]] || install -m 0644 "$POOL_SOURCE" "$POOL_TARGET"

  if ! diff -q "$POOL_SOURCE" "$POOL_TARGET" >/dev/null 2>&1; then
    install -m 0644 "$POOL_SOURCE" "$POOL_TARGET"
    ok 'pool file refreshed from deploy/php/php8.3-fpm-supa-cpns.conf'
  else
    ok 'pool file already matches deploy/php/php8.3-fpm-supa-cpns.conf'
  fi

  php-fpm8.3 -t >/dev/null || die 'php-fpm configuration test failed'
  systemctl reload "$FPM_SERVICE"
  ok "$FPM_SERVICE reloaded"

  for _ in $(seq 1 20); do
    [[ -S "$SOCKET" ]] && break
    sleep 0.5
  done
  [[ -S "$SOCKET" ]] || die "the pool socket $SOCKET never appeared"
  ok "pool socket present: $SOCKET"
}

cmd_smoke() {
  step "smoke: this site's block on 127.0.0.1:$STAGE_PORT, in front of the real pool"
  [[ -S "$SOCKET" ]] || die "the pool socket $SOCKET is missing — run: $0 pool"
  ensure_scratch
  mkdir -p "$STATE_DIR"

  local stage="$scratch/stage.caddy"
  {
    printf '{\n\tadmin off\n\tauto_https off\n}\n\n'
    sed "s|^$HOST {|http://127.0.0.1:$STAGE_PORT {|" "$SNIPPET"
  } > "$stage"

  caddy validate --config "$stage" --adapter caddyfile >/dev/null || die 'the site block does not validate outside the live config'
  caddy run --config "$stage" --adapter caddyfile >"$scratch/stage.log" 2>&1 &
  stage_pid=$!

  local code="" tries=0
  while (( tries < 40 )); do
    # `-w` prints 000 on its own when the connection fails, so a failed curl must
    # not append a second failure marker: "000000" would read as "answered".
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://127.0.0.1:$STAGE_PORT/" 2>/dev/null || true)"
    [[ "$code" == "200" ]] && break
    sleep 0.5
    tries=$((tries + 1))
  done
  [[ "$code" == "200" ]] || { sed -n '1,20p' "$scratch/stage.log" >&2; die "the staged Caddy never answered 200 (last status: ${code:-none})"; }

  curl -sS --max-time 30 -H 'Cache-Control: no-cache' -D "$scratch/headers" "http://127.0.0.1:$STAGE_PORT/" -o "$STATE_DIR/smoke.html" || true
  [[ -s "$STATE_DIR/smoke.html" ]] || die 'the staged Caddy answered but wrote no body'
  code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$STAGE_PORT/" || true)"

  [[ "$code" == "200" ]] && ok 'GET / answers 200' || bad "GET / answers $code"

  local bytes
  bytes="$(wc -c < "$STATE_DIR/smoke.html")"
  # A floor, not a target: it only has to be far above an error page or a
  # truncated body. The real completeness check is the closing tag below.
  (( bytes > 20000 )) && ok "page rendered through PHP-FPM ($bytes bytes)" || bad "page is suspiciously small ($bytes bytes)"

  if tail -c 40 "$STATE_DIR/smoke.html" | grep -q '</html>'; then
    ok 'the response is a complete document'
  else
    bad 'the response body does not end with </html> — it was truncated'
  fi

  if grep -q 'data-section="hero"' "$STATE_DIR/smoke.html" && grep -q 'data-section="footer"' "$STATE_DIR/smoke.html"; then
    ok 'sections rendered (hero … footer)'
  else
    bad 'a section is missing from the rendered page'
  fi

  if grep -q 'SUPA CPNS' "$STATE_DIR/smoke.html"; then
    ok 'brand string present'
  else
    bad 'brand string missing'
  fi

  if grep -qiE 'Fatal error|Uncaught|Whoops, looks like|Stack trace' "$STATE_DIR/smoke.html"; then
    bad 'the page contains a PHP error'
  else
    ok 'no PHP error in the response'
  fi

  if grep -q 'noindex, nofollow' "$STATE_DIR/smoke.html"; then
    ok 'noindex meta present (SITE_INDEXABLE is false)'
  else
    ok 'no noindex meta — the app is configured indexable'
  fi

  check_references "http://127.0.0.1:$STAGE_PORT" "$STATE_DIR/smoke.html"

  code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$STAGE_PORT/index.php")"
  [[ "$code" == "200" ]] && ok 'GET /index.php answers 200 (PHP is handled, not served)' || bad "GET /index.php answers $code"

  code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$STAGE_PORT/this-path-does-not-exist")"
  [[ "$code" == "404" ]] && ok 'an unknown path is a real 404' || bad "an unknown path answers $code instead of 404"

  local cache
  cache="$(curl -s -o /dev/null -D - "http://127.0.0.1:$STAGE_PORT/" | tr -d '\r' | awk 'tolower($1)=="cache-control:"{ $1=""; print; exit }' | xargs)"
  [[ "$cache" == "public, max-age=300" ]] && ok "page Cache-Control: $cache" || bad "page Cache-Control is '$cache'"

  local asset
  asset="$(php "$PROJECT_ROOT/deploy/reference-urls.php" --base="http://127.0.0.1:$STAGE_PORT" < "$STATE_DIR/smoke.html" | grep '^/build/assets/.*\.\(css\|js\)$' | head -1)"
  if [[ -n "$asset" ]]; then
    cache="$(curl -s -o /dev/null -D - "http://127.0.0.1:$STAGE_PORT$asset" | tr -d '\r' | awk 'tolower($1)=="cache-control:"{ $1=""; print; exit }' | xargs)"
    [[ "$cache" == "public, max-age=31536000, immutable" ]] && ok "built asset Cache-Control: $cache" || bad "built asset Cache-Control is '$cache'"
  else
    bad 'the page references no built stylesheet or script'
  fi

  normalize_origin "$STATE_DIR/smoke.html" > "$STATE_DIR/smoke.sha256"
  ok "smoke render recorded (sha256 $(cut -c1-12 < "$STATE_DIR/smoke.sha256"))"

  kill "$stage_pid" 2>/dev/null || true
  wait "$stage_pid" 2>/dev/null || true
  stage_pid=""
}

cmd_enable() {
  need_root enable
  step "enable: publish the Laravel app on $HOST"
  cmd_build
  cmd_pool
  cmd_smoke

  [[ $fail_count -eq 0 ]] || die "$fail_count smoke check(s) failed — the live Caddyfile was not touched"

  step 'enable: swap the site block in the live Caddyfile'
  ls -1 "$CADDYFILE".bak-* >/dev/null 2>&1 || cp -a "$CADDYFILE" "$CADDYFILE.bak-initial"

  local backup="$CADDYFILE.bak-$HOST-$(date +%Y%m%d-%H%M%S)"
  cp -a "$CADDYFILE" "$backup"
  ok "backed up: $backup"

  cp -a "$CADDYFILE" "$CADDYFILE.new"
  replace_block "$CADDYFILE.new" "$SNIPPET"
  mv "$CADDYFILE.new" "$CADDYFILE"
  chown root:root "$CADDYFILE"
  chmod 0644 "$CADDYFILE"
  ok 'site block replaced (the static clone block is kept in deploy/caddy/ for rollback)'

  reload_caddy
  ok 'caddy reloaded'

  cmd_verify

  step 'enable: neighbours'
  local neighbour code reachable=0
  for neighbour in "${NEIGHBOURS[@]}"; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$neighbour/" || true)"
    [[ "$code" =~ ^(200|301|302|307|308|403|404)$ ]] && reachable=$((reachable + 1)) || bad "$neighbour answers $code after the reload"
  done
  [[ $reachable -eq ${#NEIGHBOURS[@]} ]] && ok "all ${#NEIGHBOURS[@]} other sites on this host still answer" || true

  # Expiry is evaluated on the read path, so the application is correct without any
  # scheduler. Uncomment the line below (and the schedule:run entry it installs) only if
  # you want abandoned attempts swept even when nobody opens the app.
  #   sudo -u ahlfs -H bash -lc "cd $PROJECT_ROOT && php artisan cpns:expire-attempts"

  step 'result'
  printf '%s ok, %s failed\n' "$pass_count" "$fail_count"
  [[ $fail_count -eq 0 ]] || die "the deploy finished with $fail_count failed check(s)"
}

cmd_disable() {
  need_root disable
  step 'disable: hand the hostname back to the previous site block'
  [[ -f "$ROLLBACK_SNIPPET" ]] || die "no rollback block at $ROLLBACK_SNIPPET"

  cp -a "$CADDYFILE" "$CADDYFILE.bak-$HOST-disable-$(date +%Y%m%d-%H%M%S)"
  replace_block "$CADDYFILE" "$ROLLBACK_SNIPPET"
  reload_caddy
  ok 'the previous block is live again'

  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/")"
  [[ "$code" == "200" ]] && ok "GET $URL/ answers 200" || bad "GET $URL/ answers $code"
}

cmd_verify() {
  step "verify: $URL"
  ensure_scratch
  mkdir -p "$STATE_DIR"
  local live="$scratch/live.html" code

  code="$(curl -sS --max-time 30 -H 'Cache-Control: no-cache' -o "$live" -w '%{http_code}' "$URL/" || true)"
  [[ "$code" == "200" ]] && ok "GET / answers 200 over https" || { bad "GET / answers $code"; return; }

  if [[ -f "$STATE_DIR/smoke.sha256" ]]; then
    local live_hash smoke_hash
    live_hash="$(normalize_origin "$live")"
    smoke_hash="$(cat "$STATE_DIR/smoke.sha256")"
    if [[ "$live_hash" == "$smoke_hash" ]]; then
      ok "the live page is byte-identical to the smoke render ($(cut -c1-12 <<<"$live_hash"))"
    else
      bad "the live page differs from the smoke render"
    fi
  else
    bad 'no smoke render recorded — run the smoke step first'
  fi

  grep -q 'SUPA CPNS' "$live" && ok 'brand string present' || bad 'brand string missing'
  grep -q 'data-section="footer"' "$live" && ok 'the page carries the footer section' || bad 'footer section missing'

  check_references "$URL" "$live"

  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$URL/index.php")"
  [[ "$code" == "200" ]] && ok 'GET /index.php answers 200 (PHP-FPM is in front of the app)' || bad "GET /index.php answers $code"

  local path
  for path in /this-path-does-not-exist /tools/build-brand-assets.mjs /package.json /.env /recon/; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$URL$path")"
    [[ "$code" == "404" ]] && ok "$path → 404" || bad "$path answers $code"
  done

  local json
  json="$(curl -s -o /dev/null -D - "https://$HOST/assets/favicon.ico" -w '' | tr -d '\r' | awk 'tolower($1)=="cache-control:"{ $1=""; print; exit }' | xargs)"
  [[ "$json" == "public, max-age=604800" ]] && ok "image Cache-Control: $json" || bad "image Cache-Control is '$json'"

  local cert
  cert="$(echo | openssl s_client -servername "$HOST" -connect "$HOST:443" 2>/dev/null | openssl x509 -noout -issuer -enddate 2>/dev/null | tr '\n' ' ' || true)"
  [[ -n "$cert" ]] && ok "certificate: $cert" || bad 'could not read the TLS certificate'

  [[ -s "$ACCESS_LOG" ]] && ok "access log written: $ACCESS_LOG" || bad "no access log at $ACCESS_LOG"
}

# --- the host's environment --------------------------------------------------
#
# `.env` is gitignored, so nothing committed can state what production needs —
# and every one of these is a deployment that looks fine and is not: an English
# locale, a cached config that bakes APP_DEBUG=true into every error page, a
# database the pool cannot write, or a session lifetime shorter than the sitting
# it has to hold. `status` asserts them rather than printing them, and a wrong one
# is a `bad` line, so the run ends in "N ok, M failed" and a non-zero exit.
#
# They are stated together for the operator in docs/WORKING-NOTES.md.
env_value() {
  local line
  line="$(grep -E "^$1=" "$PROJECT_ROOT/.env" 2>/dev/null | tail -1 || true)"
  line="${line#*=}"
  line="${line%\"}"
  line="${line#\"}"
  printf '%s' "$line"
}

# The sitting length, from the blueprint itself rather than repeated here: it is
# the one number a session lifetime has to be able to outlast.
sitting_minutes() {
  php -r '$config = require $argv[1]; echo (int) $config["duration_minutes"];' \
    "$PROJECT_ROOT/config/cpns.php" 2>/dev/null || printf '100'
}

check_env_requirements() {
  step 'environment: the values only the host can supply'

  if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
    bad "no $PROJECT_ROOT/.env — the application has no configuration at all"
    return
  fi

  local value

  value="$(env_value APP_LOCALE)"
  [[ "$value" == "id" ]] && ok 'APP_LOCALE=id (pages and validation messages are Indonesian)' \
    || bad "APP_LOCALE is '${value:-absent}' — every page falls back to English (want: id)"

  value="$(env_value APP_ENV)"
  [[ "$value" == "production" ]] && ok 'APP_ENV=production' \
    || bad "APP_ENV is '${value:-absent}' (want: production)"

  value="$(env_value APP_DEBUG)"
  [[ "$value" == "false" ]] && ok 'APP_DEBUG=false' \
    || bad "APP_DEBUG is '${value:-absent}' — stack traces and environment values reach participants"

  value="$(env_value APP_KEY)"
  [[ -n "$value" ]] && ok 'APP_KEY is set' \
    || bad 'APP_KEY is empty — sessions, cookies and encrypted values cannot be written'

  # The SQLite file the migrations target, and the only one the pool may write. A
  # relative DB_DATABASE resolves from the repository root; an absent one is
  # config/database.php's database_path('database.sqlite').
  local connection db path

  connection="$(env_value DB_CONNECTION)"
  db="$(env_value DB_DATABASE)"

  [[ "$connection" == "sqlite" ]] && ok 'DB_CONNECTION=sqlite' \
    || bad "DB_CONNECTION is '${connection:-absent}' (want: sqlite)"

  if [[ "$connection" == "sqlite" ]]; then
    if [[ -z "$db" ]]; then
      path="$PROJECT_ROOT/database/database.sqlite"
      ok 'DB_DATABASE is unset — the config default database/database.sqlite applies'
    elif [[ "$db" == /* ]]; then
      path="$db"
    else
      path="$PROJECT_ROOT/$db"
    fi

    if [[ -f "$path" ]]; then
      if as_owner "test -w $(printf '%q' "$path")"; then
        ok "the database file is writable by the project owner: $path"
      else
        bad "the database file is NOT writable by the project owner: $path — migrations and every write fail"
      fi
    elif [[ -d "$(dirname "$path")" ]]; then
      ok "no database file yet at $path — the first migrate creates it"
    else
      bad "no database file and no directory at $(dirname "$path") — the migrations cannot create it"
    fi
  fi

  local wanted actual

  wanted="$(sitting_minutes)"
  actual="$(env_value SESSION_LIFETIME)"
  actual="${actual:-120}"

  if [[ "$actual" =~ ^[0-9]+$ ]] && (( actual >= wanted )); then
    ok "SESSION_LIFETIME=${actual} minutes — outlasts the ${wanted}-minute sitting"
  else
    bad "SESSION_LIFETIME is '${actual}' but a sitting lasts ${wanted} minutes — a participant would be signed out mid-exam"
  fi
}

cmd_status() {
  step "status: $HOST"
  printf 'services      : caddy=%s %s=%s\n' "$(systemctl is-active caddy)" "$FPM_SERVICE" "$(systemctl is-active "$FPM_SERVICE")"
  printf 'pool socket   : %s\n' "$([[ -S "$SOCKET" ]] && echo present || echo MISSING)"
  printf 'doc root      : %s\n' "$(grep -A1 "^$HOST {" "$CADDYFILE" | awk '/root \*/{print $3}')"
  printf 'live          : HTTP %s\n' "$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/" || echo 000)"
  if grep -q "supa-cpns/public" "$CADDYFILE"; then
    printf 'served by     : the Laravel app\n'
  else
    printf 'served by     : something else — `grep -A3 "^%s {" %s`\n' "$HOST" "$CADDYFILE"
  fi
  if [[ -f "$POOL_TARGET" ]]; then
    diff -q "$POOL_SOURCE" "$POOL_TARGET" >/dev/null 2>&1 && printf 'pool file     : matches the repository\n' || printf 'pool file     : DIFFERS from the repository\n'
  else
    printf 'pool file     : not installed\n'
  fi
  printf 'smoke render  : %s\n' "$([[ -f "$STATE_DIR/smoke.sha256" ]] && cut -c1-12 < "$STATE_DIR/smoke.sha256" || echo 'none recorded')"
  printf 'migrations    : %s pending\n' "$(pending_migrations)"
  printf 'url / index   : %s\n' "$(grep -E '^(APP_URL|SITE_INDEXABLE)=' "$PROJECT_ROOT/.env" 2>/dev/null | tr '\n' ' ' || true)"

  check_env_requirements
}

command="${1:-}"
case "$command" in
  build) cmd_build ;;
  migrate) cmd_migrate ;;
  pool) cmd_pool ;;
  smoke) cmd_smoke ;;
  enable) cmd_enable ;;
  disable) cmd_disable ;;
  verify) cmd_verify ;;
  status) cmd_status ;;
  *) usage; exit 1 ;;
esac

if [[ $fail_count -gt 0 ]]; then
  printf '\n%s ok, %s failed\n' "$pass_count" "$fail_count"
  exit 1
fi

if [[ "$command" =~ ^(build|migrate|pool|smoke|verify|status)$ ]]; then
  printf '\n%s ok, 0 failed\n' "$pass_count"
fi
