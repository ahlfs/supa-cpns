# SUPA CPNS

Marketing site for **SUPA CPNS** — a Laravel 13 + Blade + Tailwind v4 rebuild of a
captured marketing page. The design is reproduced from measurements taken in a
browser; the implementation is this repository's own.

The same application also carries the **CPNS exam-tryout module**: participant
registration, a full 110-item SKD sitting on a server-owned clock with grading and
per-subtest analysis, and an admin panel for the question bank, bulk imports and
exam packages. Decisions and scope live in `docs/SPEC-CPNS-TRYOUT.md`.

```
php artisan serve            # http://127.0.0.1:8000
npm run dev                  # Vite dev server with HMR
npm run build                # production bundle into public/build
php artisan test             # feature tests (structure, assets, ordering)
./vendor/bin/pint --test     # code style
php artisan cpns:admin you@example.com   # create the first admin, then open /admin
```

Internals — the styling rules, the behaviour hooks, what to run before claiming a
change works and how to publish it — are in `docs/WORKING-NOTES.md`.

## How the site is built

| Concern | Where it lives |
|---|---|
| Page structure | `resources/views/home.blade.php` + one file per section in `resources/views/sections/` |
| Site chrome | `resources/views/components/partials/` (header, footer, floating actions) |
| Copy, links, images | `resources/content/site.php` and `resources/content/home/<section>.php` |
| Design tokens | `resources/css/app.css` (`@theme` block) |
| Interactions | `resources/js/modules/` (header, program tabs, carousels, back to top) |
| Icons | `resources/icons/*.svg`, exposed as `<x-icon name="…" />` |
| Fonts | `resources/fonts/*.woff2` declared in `resources/css/fonts.css` |
| Images | `public/assets/` |

**Editing content never means editing Blade.** Every section reads a PHP data file
from `resources/content/home`: change a headline, a price, a link or an image path
there and the page follows. `resources/content/site.php` holds what appears in
more than one place — brand name, contact details, calls to action, social links,
legal links.

### Adding or removing a section

1. Add `resources/content/home/<name>.php` returning an array.
2. Add `resources/views/sections/<name>.blade.php` whose root element carries
   `data-section="<name>"` and receives that array as `$<name>`.
3. Include it in page order in `resources/views/home.blade.php`.
4. Add `<name>` to `Tests\Feature\HomepageTest::SECTIONS` so the order and the
   asset check keep covering it.

### Styling rules

- Tailwind utilities only — no component CSS files. Values that Tailwind does not
  ship are written as arbitrary values (`text-[#69727d]`, `lg:px-[22px]`).
- One breakpoint per property, and only the largest: base + `lg:` (which is
  **992px**, redefined in `app.css` to match the design) and optionally a
  `max-sm:` / `max-[600px]:` override. Two overlapping min-width variants on one
  property silently lose whichever Tailwind emits first.
- State that changes appearance is a data attribute styled with a data variant
  (`data-[scrolled=true]:shadow-lg`), which is also how the JS drives it.

## Verification

```bash
php artisan test                                   # structure, links, brand, assets, noindex
node tools/page-shots.mjs http://127.0.0.1:8000    # every marketing page at 1440 and 390
node tools/live-smoke.mjs --url=…                  # drive the deployed page: menu, panels, drawer
```

`php artisan test` is the primary gate: it renders the marketing pages, walks every
link they contain and fails on a dead one, and refuses to let a page name another
organisation or link off-site (`tests/Feature/BrandIdentityTest.php`).
`tools/page-shots.mjs` screenshots the pages in a real browser and reports broken
images, off-site links and console errors; `tools/live-smoke.mjs` drives the
interactions and reads the state each module publishes.

Supporting instruments:

| Tool | Use |
|---|---|
| `tools/live-smoke.mjs --url=…` | drive the deployed page: menu, panels, drawer, back-to-top, console errors |
| `tools/exam-smoke.mjs` | drive one full sitting: register, answer every item, submit, read the result |
| `tools/admin-entry-smoke.mjs` | the administrator's way into the panel: both links, four widths, header row measured for overlap |
| `tools/header-geometry.mjs` | is the brand mark centred in the bar, what the bar offers, and whether the footer's mark anchor is a line box |
| `tools/probe-element.mjs <url> <selector>` | dump box + computed style of a subtree in a real browser |
| `tools/probe-breakpoints.mjs --root=… --selector=…` | print only the widths where a property changes |
| `tools/class-coverage.mjs` | every class used in the markup has a rule in the built stylesheet |
| `tools/icon-check.mjs` | every vendored icon parses and renders |
| `tools/sandbox.sh <name> <port>` | an isolated copy of the app for parallel work |

## Deploying

Production is this host. https://cpns.buatjalan.xyz/ is served by the system
Caddy, which terminates TLS and hands PHP to this app's own PHP-FPM pool
(`php8.3-fpm-supa-cpns`, socket `/run/php/php8.3-fpm-supa-cpns.sock`), with
`public/` as the document root — nothing outside it (vendor, storage, .env) is
reachable over HTTP.

```bash
sudo bash deploy/host-cpns.sh enable     # the whole deploy, and its verification
bash deploy/host-cpns.sh status          # what is live right now
sudo bash deploy/host-cpns.sh migrate    # just the database migrations, as the owner
sudo bash deploy/host-cpns.sh disable    # hand the hostname back to the block that preceded it
```

`enable` builds the front end and caches the framework as the project owner,
**applies the module's database migrations** (also as the project owner — a
failure here stops the deploy), installs the pool file from `deploy/php/`,
smoke-tests the site block on loopback port 8099 **in front of the real pool** —
fetching every asset, stylesheet and built file the page references — then
replaces the hostname's block in `/etc/caddy/Caddyfile` (backed up first,
validated before the reload), and finally checks the public URL byte for byte
against what the smoke test rendered and re-checks the eleven other sites this
Caddy also serves. The migrations come before the smoke test on purpose: the
smoke test fetches only `/`, which renders from `resources/content` and touches no
table, so a deploy that skipped them would report success while `/masuk`,
`/daftar`, `/tryout` and `/admin` answered 500 on a missing table. Re-running
`enable` is safe.

Rollback is `disable`, which writes the Caddy block that used to serve this
hostname back from `deploy/caddy/<host>.static-clone.caddy` and reloads. That
file is a snapshot of the host's state taken when `enable` first published this
app — host state, like `.env`, not source, so it is not in this repository;
`disable` needs the copy on the machine that has already deployed.

The host's own `.env` is not in the repository, so `status` asserts the values
production needs — `APP_LOCALE=id`, `APP_ENV=production`, `APP_DEBUG=false`, an
`APP_KEY`, a `DB_CONNECTION`/`DB_DATABASE` pair pointing at the writable SQLite file
the migrations target, and a `SESSION_LIFETIME` long enough for a 100-minute sitting.
It prints `FAIL` for each wrong one and exits non-zero; the list and its reasoning
are in `docs/WORKING-NOTES.md`.

Going live in search engines is one environment value — `SITE_INDEXABLE=true` —
followed by re-running `enable` (the config cache is built from `.env`).

## Images

`public/assets` holds this project's own artwork and nothing else: the favicon
set, the share card, the section illustrations, the feature and metric icons. All
of it is generated by `tools/build-brand-assets.mjs` from the brand mark — the
wordmark in the logo is live text and the cloud is a single vendored SVG — so
regenerating an image means editing the generator, not the file. What is
third-party, and under which licence, is listed in `docs/BRAND-ASSETS.md`.

## Search engines

`SITE_INDEXABLE` (see `config/site.php`) is `false` by default, so preview deploys
serve `noindex, nofollow`. Set it to `true` when the site goes live. Analytics IDs
are read from the environment (`ANALYTICS_*`) and every snippet is skipped while
they are empty.
