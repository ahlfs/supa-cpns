# Working notes for an agent (or a new contributor)

`README.md` is the operator's guide — how the site is built, where content lives,
how to add a section. This file is what to read before touching the code, so a
change lands in the house style instead of beside it.

## Non-negotiables

1. **Tailwind utilities only.** No new CSS files, no BEM class names, no
   `<style>` blocks. Values Tailwind does not ship are written as arbitrary
   values (`h-[52px]`, `text-[#69727d]`) or added as a token in
   `resources/css/app.css`.
2. **One breakpoint per property, and only the largest.** Base value + `lg:`
   (`lg` is **992px** here, redefined in `app.css`) plus optionally a `max-sm:` /
   `max-[480px]:` override. Never `md:` and `lg:` on the same property: Tailwind
   decides the emitted order, not the class attribute, so one of them silently
   loses. `tools/probe-breakpoints.mjs` prints the widths where a property
   actually changes — use it instead of guessing.
3. **Content lives in `resources/content/`**, never inline in Blade. Every
   visible string, URL and image path comes from `resources/content/site.php` or
   `resources/content/home/<section>.php`. Brand text is `$site['brand']`.
4. **Images go through `<x-ui.image :image="…" />`**, icons through
   `<x-icon name="…" />` with a file in `resources/icons/`. Never an emoji, never
   an icon font, never a remote URL.
5. **Behaviour hooks are `data-*` attributes.** The JS modules in
   `resources/js/modules/` already exist; markup provides the hooks:
   `[data-site-header]` + `data-scrolled`, `[data-nav-item]`,
   `[data-menu-trigger]`, `[data-menu-panel]`, `[data-drawer-toggle]`,
   `[data-mobile-drawer]`, `[data-drawer-accordion]`, `[data-drawer-sublist]`,
   `[data-program-tabs]` + `[data-tab]`, `[data-program-panel]`,
   `[data-carousel="…"]` + `[data-carousel-track]`, `[data-carousel-slide]`,
   `[data-carousel-dots]`, `[data-carousel-prev]`, `[data-carousel-next]`,
   `[data-back-to-top]`. State that changes appearance is a `data-*` value styled
   with a Tailwind data variant (`data-[scrolled=true]:shadow-lg`), never a class
   toggled at runtime.
6. **Every section root carries `data-section="<name>"`** — the layout uses it,
   the tests assert on it, and the browser tools find the section by it.
7. **Keep the reference's accessibility**: the same `aria-*`, roles, alt text,
   heading order and keyboard behaviour.

## Before claiming a change works

```bash
npm run build                                  # required for any Blade or CSS change
php artisan test                               # structure, links, brand, assets, noindex
./vendor/bin/pint --test                       # code style
node tools/page-shots.mjs http://127.0.0.1:8000  # real browser: broken images, console, off-site links
node tools/live-smoke.mjs --url=http://127.0.0.1:8000/   # menu, panels, drawer, back-to-top
node tools/header-geometry.mjs https://cpns.buatjalan.xyz  # mark centred in the bar, bar entries, footer mark
```

`docs/PORTING-CONTRACT.md` is the full contract for porting or editing a section.
Layout-sensitive edits are judged by measurement (`tools/probe-element.mjs`, the
`.probe/` capture set the contract points at), not by eye.

## Deploying

Production is this host. `deploy/host-cpns.sh enable` publishes
https://cpns.buatjalan.xyz/ through the system Caddy and the PHP-FPM pool
`php8.3-fpm-supa-cpns`: it builds the front end, applies the database migrations,
installs the pool file, backs the Caddyfile up, smoke-tests the new site block on
a spare loopback port first, validates before reloading, and verifies the result
over the public URL. `deploy/host-cpns.sh status` reports what is live and
`… disable` hands the hostname back to the static clone.

In order, `enable` does: (1) `npm run build` as the project owner; (2)
`php artisan migrate --force` **also as the project owner**, which stops the
deploy if it fails; (3) `php artisan optimize`; (4) install/reload the PHP-FPM
pool; (5) smoke-test the new site block on 127.0.0.1:8099 in front of the real
pool; (6) replace the hostname's block in `/etc/caddy/Caddyfile` and reload;
(7) verify over https and re-check the eleven neighbour sites. Step 2 exists
because the smoke and verify steps fetch only `/`, which renders from
`resources/content` and touches no table: without it a deploy would print
"0 failed" while `/masuk`, `/daftar`, `/tryout` and `/admin` answered 500 on a
missing table. A migration failure is a deploy failure. Re-running `enable` is
safe — `migrate` applies nothing once the schema is current. `deploy/host-cpns.sh
migrate` runs that step on its own, and `status` prints how many migrations are
pending.

## CPNS tryout module

The exam-tryout module was built from two documents, and they are still the place
to look before changing it: `docs/SPEC-CPNS-TRYOUT.md` is the specification — the
SKD blueprint, the scoring rules, the acceptance list — and
`docs/plans/2026-11-15-cpns-tryout-module.md` is the task-by-task plan that built
it. Anything that disagrees with a threshold in `config/cpns.php` is wrong; no
number from the blueprint is written into a view or a controller.

**The clock is the server's.** An attempt owns `started_at` and `expires_at`, and
expiry is evaluated on the read path: opening the room, answering, finishing and
opening the analysis all ask the attempt whether it is overdue first. That means
the application is correct with no scheduler running at all, and a candidate
cannot buy time by closing the tab or by changing the device clock. The optional
`php artisan cpns:expire-attempts` command (and the commented line in the deploy
script) exists only to sweep attempts that were abandoned forever, so the admin
dashboard's counts do not drift — it is a complement, never a dependency.

**Scores freeze at submit; text does not.** When an attempt is graded its per-item
and total scores are written down and never recomputed, so editing a question's
stem, options or key afterwards changes what the review page displays but never
what a finished candidate scored. That is why deleting an already-answered
question is refused rather than allowed to erase history, and why the bank shows
which packages use an item before it will let it go.

**Composition locks at the first attempt.** The moment anyone sits a package the
list and order of its items freeze — two sittings of different questions are not
comparable — while the item text stays editable from the bank. A different order
means a new package.

**Imports are all-or-nothing.** Every row is validated before anything is written;
one bad row refuses the whole block with the failing line numbers and both cell
counts named. The exact-cell-count rule and its remedies (quote a stem carrying a
comma; carry a trailing comma when a cell is empty) are in
`docs/QUESTION-IMPORT-FORMAT.md`.

**The first administrator comes from the command line:** `php artisan cpns:admin
you@example.com`. There is no self-service path into `/admin`.

**`password_reset_tokens` is intentionally unused.** Email is dead on this host, so
"forgot my password" cannot work and is not offered. An administrator resets an
account from the panel instead and hands over a temporary password in person; the
account is held at the change-password form until that password is replaced.

**All user-facing strings are Indonesian, including validation.** `APP_LOCALE` is
`id` and `lang/id/validation.php` translates Laravel's own validation messages and
names every column the way its label spells it, so an `@error` reads "Nama lengkap
wajib diisi." and not "name wajib diisi."; `APP_FALLBACK_LOCALE` stays `en` as a
safety net, not a second language anyone is meant to see.

`.env` is gitignored, so `APP_LOCALE=id` in this checkout proves nothing about a
server. `config/app.php` defaults to `id` and `.env.example` ships `APP_LOCALE=id`,
but a host whose own `.env` still says `APP_LOCALE=en` wins over that default, and
`php artisan optimize` — which `deploy/host-cpns.sh enable` runs — freezes the value
into the config cache until it is cleared. For the locale to take effect in
production, the server's `$PROJECT_ROOT/.env` needs `APP_LOCALE=id` and then a fresh
`php artisan optimize` (or `php artisan config:clear`).

### The host's environment

Nothing committed can state these: `.env` is gitignored, so the server's own file is
the only copy, and every one of them is a deployment that looks fine and is not.
They are listed together because they are one requirement set, and
`deploy/host-cpns.sh status` **asserts** each of them — a wrong value prints a `FAIL`
line and the run exits non-zero, rather than being printed and stepped over:

| Value | Required | What breaks without it |
|---|---|---|
| `APP_LOCALE` | `id` | Every page and every validation message falls back to English |
| `APP_ENV` | `production` | The app runs in the local environment (debug behaviour, no config cache) |
| `APP_DEBUG` | `false` | Stack traces, environment values and file paths reach participants |
| `APP_KEY` | set | Sessions, cookies and encrypted values cannot be written |
| `DB_CONNECTION` | `sqlite` | The migrations target a database the pool cannot reach |
| `DB_DATABASE` | the writable SQLite file the migrations target | Writes fail; a relative path resolves from the repository root, and an absent one means `config/database.php`'s `database/database.sqlite`. `status` checks the file is writable **by the project owner**, which is who runs the migrations |
| `SESSION_LIFETIME` | at least the sitting length (100 minutes) | A participant is signed out mid-exam — the clock keeps running, so the sitting cannot be finished |

The sitting length is read from `config/cpns.php` (`duration_minutes`) rather than
repeated in the deploy script. The database driver is what makes `SESSION_LIFETIME`
matter at all: a signed-in device holds a row in `sessions` for the whole lifetime,
which is also why an administrator's password reset deletes that account's rows.

`deploy/host-cpns.sh migrate` applies the schema to whichever SQLite file
`DB_DATABASE` points at, so a wrong value there is a deploy that migrates one file
and serves another — which is what the check exists to catch.

`tools/exam-smoke.mjs` drives a whole sitting in a real browser against whatever
`BASE` it is given (loopback first); it is separate from `tools/live-smoke.mjs`
because the marketing smoke test needs neither an account nor a database write
while this one needs both.

## The interface layer

**A stale `public/build` looks like broken markup.** Caddy serves the repository
root, so the deployed stylesheet is whatever is in `public/build` — and because
the scanset is `source(none)` plus three `@source` lines, a class the build has
never seen simply has no rule. The symptom is a page that renders its markup
correctly and looks unstyled: full-width cards, invisible field borders, a
demo-populated page that appears unfinished. It happened twice — the account
pages after the module merged, and the exam room's bottom sheet, whose
`pointer-events`/`translate` utilities were missing so the closed sheet sat over
the answer options and swallowed taps on a phone. In both cases the markup was
right; the stylesheet was older than the views. `npm run build` is part of a
deploy for this reason, and `tools/class-coverage.mjs <base> <paths…>` answers
"is the bundle current" directly: it walks the `@layer` blocks in the loaded
sheets, collects every class name they define, and reports the classes the DOM
uses that no rule covers. It reads the live DOM, so it catches classes a
view-level grep cannot.

**The fixed header is reserved in one place.** The header is `fixed`, so it pushes
nothing: each page clears it itself. The marketing sections do that in their own
padding (the hero's `pt-[110px]`), and the application pages go through
`resources/views/components/ui/app-page.blade.php`, which holds the measured
offset (~28px under the pill at both breakpoints) plus the `centered` shape the
account pages use. Change the pill's geometry and that component is the one edit.

**Form controls use `--color-field`, not `--color-line`.** `--color-line`
(`#dce5ef`) measures 1.27:1 on white, which is right for a card hairline and below
the 3:1 that WCAG 1.4.11 asks of a control edge; `--color-field` (`#7d8ea3`)
measures 3.35:1. Cards and dividers keep `border-line`. `tools/auth-visual.mjs`
measures the account pages in a real browser (card geometry, the gap under the
header, control styling, the checkbox) and drops screenshots beside the numbers.

## Two traps in this shell

Both cost real time; both are silent.

**An exported `DB_DATABASE` beats `.env`.** If the shell that runs `php artisan`
has `DB_DATABASE` exported — a scratch value from an earlier smoke test is the
usual source — artisan talks to that database while php-fpm, which never sees the
variable, serves the one in `.env`. A migration then reports "nothing to migrate"
against a database the site is not using. Run `env -u DB_DATABASE php artisan …`
when anything depends on which file is touched, and check the variable is unset
first.

**A cached config pins the database into the test run.** With
`bootstrap/cache/config.php` present, `phpunit.xml`'s `DB_DATABASE=:memory:` is
read too late to matter, so the suite runs against whatever the cache names —
which on this host is the live database, with `RefreshDatabase` in front of it.
Run `php artisan config:clear` before the suite, or let the deploy script's
`optimize` step be the last thing that touches the cache.

## The bank soal

**The question bank is authored content, not data.** `resources/content/questions/`
holds one file per subtest — `twk.php` (30 item), `tiu.php` (35), `tkp.php` (45) —
each returning a list of items with a stable `code`, and `App\Support\QuestionBank`
reads and validates them. `php artisan cpns:seed-bank` writes them into the database;
`--prune` also clears items that are no longer in the bank (deleting the ones nothing
depends on, deactivating the ones that have been answered or sit in a sat package,
because the pivot's foreign key refuses that delete anyway).

Two shapes, matching what the model takes: keyed items carry `'key' => 'C'` and
plain option strings, weighted TKP items carry `'options' => ['A' => ['body' => …,
'score' => 5], …]` and no key. The loader refuses a bank with any of: a key on a TKP
item, weights on a keyed item, a TKP ladder that is not exactly 1–5, an unknown
subcategory, a duplicate code, a missing option, or a thin explanation — and it
reports all of them at once, because fixing a bank one error per run is an evening.

**The answer must not always sit on the same letter.** The first draft of this bank
keyed every item on A, and every TKP item put its weight-5 option first: a
participant could have scored 550/550 without reading a question, and no PHP test
would have noticed, because they all passed. Option order is rotated once when the
files are authored, and `ShippedQuestionBankTest` keeps it honest — it asserts that
each letter is the key at least four times per subtest, that no run of identical
keys is longer than two, and that the heaviest TKP option is spread the same way.
Treat a green suite on a freshly written bank as no evidence at all until that test
exists.

**Deploy does not re-seed.** `host-cpns.sh enable` migrates, and that is all it does
to the database: re-seeding on deploy would overwrite whatever an administrator had
edited in the panel for those same items. After merging a bank change, run
`env -u DB_DATABASE php artisan cpns:seed-bank --prune` on the host by hand.

## A malformed icon is invisible to every PHP test

`resources/icons/youtube.svg` shipped with a broken path — the cubic bezier had lost
two of its six numbers (`C117.22 448 288 448s…`), and the sibling
`youtube-brand.svg` still had them. The file is well-formed XML, Blade inlines it
without complaint, `IconsTest` passes, and PHP never looks at the geometry; the only
symptom is a console error in the browser and a glyph that draws wrong.
`node tools/icon-check.mjs` loads each icon on its own page and prints the ones
Chromium complains about, which is how this one was found. The exam smoke test's
`no console errors during the whole sitting` check is what surfaced it.

## Two more traps from the sitting

**A text-editing script can delete a file's `<?php` header, and `php -l` will not
say so.** A one-off script that rewrote `tkp.php` by splitting it at each
`'code' =>` marker dropped everything before the first item — including the opening
tag and the file's documentation. A PHP file with no opening tag is just output:
`php -l` reports no syntax errors, and the loader sees `require` return `1` instead
of the array. Anything that rewrites a content file has to keep its preamble, and
the way to check is `php -r '$v = require "…"; var_dump($v);'`, not `php -l`.

**On a phone viewport, the navigator's cells are off-screen while the sheet is
closed.** `tools/exam-smoke.mjs` walks a whole sitting, and every attempt to do it
the direct way cost a run: clicking navigator cells cannot work at 390×844 because
the cells live in the bottom sheet, so Playwright waits forever for a click that can
never land; pressing NEXT instead works, but NEXT moves relative to wherever the page
thinks it is, so under load two quick presses become one skipped item and the sitting
ends with a blank the finish button refuses. The script now opens the sheet and
addresses each item by number — an address cannot skip — and it waits for the
**answered counter** after every option click rather than for the cell to turn green,
because the colour is set optimistically and reverts when a write fails. When the
sitting is incomplete the check names the blank items, so the failure says which item
rather than "not all answered". A green run of this script is the only thing that
proves a whole sitting still works; the PHP suite cannot see layout.


## The site's own identity (post-rebrand)

**Content files name a route, never a path.** A link is written as
`'route' => 'tryout.index'` and `App\Support\ContentRepository` resolves it to a
URL when the file is read, relative, at read time. Typing `/tryout` would work
until somebody renamed the route; typing it in nine files would work until
somebody renamed the route and missed five. An explicit `'url'` still wins, which
is how an in-page anchor (`'/#program'`) travels.

**Everything optional is empty by default and the view hides it.** `contact`,
`socials` and `legal.links` ship empty, and the footer renders the address block,
the policy row and the social row only when they hold something. Filling them in
is a content edit, never a view edit. Do not "helpfully" seed a placeholder
address or a handle nobody owns: an absent block is honest, a wrong one is a
claim.

**Brand strings come from `$site['brand']`.** The only exception is the wordmark
in `resources/views/components/ui/brand-mark.blade.php`, which is the mark itself.
`tests/Feature/BrandIdentityTest.php` enforces the rest: every marketing page
carries the cloud glyph (asserted by its path data, so losing the logo cannot
pass because the text is still there), no page names another organisation, and
every link in the chrome stays on this site and answers 200 or a redirect.

**Marketing artwork is generated, not vendored.** `node
tools/build-brand-assets.mjs` writes the favicon, the share card, the hero and
exam-room illustrations, the program cards, the feature metrics and the floating
props; only the cloud glyph (Tabler Icons, MIT) and the Lucide icons are
third-party, and `docs/BRAND-ASSETS.md` records that. Editing a drawing means
editing the generator and re-running it, not editing the SVG by hand — a
hand-edited SVG is one `build-brand-assets` run away from being overwritten.

**Deleted: the pixel-parity tooling.** `tools/visual-parity.mjs` and
`docs/PARITY.md` measured this app against the captured reference page, matching
sections by the reference's own `asn-*` classes. The rebrand replaced the artwork,
the copy and the section list on purpose, so the tool could only fail. Rendering
is checked now by `php artisan test`, `tools/page-shots.mjs` (broken images,
off-site links, console errors at 1440 and 390) and `tools/live-smoke.mjs`.

**The deploy's smoke step had a size floor that outlived its page.** It refused
any body under 100000 bytes, which was calibrated when the homepage carried ~700
image srcsets; the rebranded page is 65 KB, so a healthy deploy died before the
live config was touched. The floor is now low enough that only an error page can
fail it, and the check that actually catches a broken response is that the body
ends with `</html>`.

## Measuring a page instead of judging one

`tools/lib/layout-measure.mjs` holds the one measurement both
`tools/layout-audit.mjs` (anonymous pages) and `tools/authed-audit.mjs` (the
pages behind a sign-in) report with, so a word like "overlap" means the same
thing in both. It answers four questions with numbers: two in-flow boxes
intersecting, an element painting past the viewport, text cropped by an
`overflow-hidden` box, and siblings in a grid with uneven heights. It also
answers a fifth that nothing else can — content hidden behind a fixed header —
which is the only check that has to be taken at `scrollY === 0`.

**Every finding it produced before it was trustworthy was false.** Five classes
of noise, each of which buried the real result, and each worth remembering
before adding a sixth:

1. The page was measured mid-scroll. Every marketing page declares
   `scroll-behavior: smooth`, so `scrollTo(0, 0)` starts an animation and
   returns immediately. The reading then described a page still travelling
   upward: scrolled-under content looked like content hidden behind the header,
   and the section gaps came out negative. `settlePage()` now forces
   `scroll-behavior: auto`, waits two frames, verifies `scrollY === 0`, and the
   report carries `scrollY` so a caller can prove where it stood.
2. The same bug lived inside `tools/page-shots.mjs` as its own copy of the
   scroll code, which is why a full-page capture showed the fixed header lying
   across a section it never covers. It shares the helper now.
3. A drawing is one element. The `circle` and `polyline` of a check mark always
   intersect.
4. A table inside an `overflow-x-auto` container is not horizontal overflow.
5. A `li` that is itself a flex row of a label, a paragraph and a badge has
   uneven heights by design; a vertical list has them too. Ragged rows now only
   counts card-sized siblings in a grid or a wrapping row.

A sixth arrived with the collapsible review: Chromium holds the content of a
closed `<details>` with `content-visibility: hidden`, so every collapsed row
still reports a full-size box and the review read as 132 elements piled on each
other — the exact opposite of what the page does. Nothing inside a closed
disclosure can be seen, so nothing inside it can collide.

**Only content a person can lose counts.** A crop inside an `aria-hidden`
subtree is a deliberate crop, and an element has to carry its own text or be a
control to be reported as hidden: a wrapper like `main` intersects every pinned
layer by construction.

## Traps that cost time

**Tests silently ran against the live database.** `bootstrap/cache/config.php`
overrides phpunit's `DB_DATABASE=:memory:`, so with the cache in place the suite
connects to `database/database.sqlite`, and every CSRF-protected POST returns
419 — 113 failures on an untouched tree. `routes-v7.php` does the same to a new
route, which is why a route can exist in `routes/web.php` and still 404 on the
live site. Clear both caches before running the suite, and rebuild them after.

**`DB_DATABASE` can be exported in the shell.** A stale export from an earlier
session overrides `.env`, so an artisan command hits a different database than
php-fpm. Always `env -u DB_DATABASE php artisan …`.

**`node --check` aborts with SIGABRT on this host**, for every file including
untouched ones. `npm run build` is the real check; a new module is wired into
`resources/js/app.js` and the bundle proves it parses.

**Verify a page with a viewport capture, not a full-page one.** A `position:
fixed` header renders once, at whatever offset the capture froze.

**Blade decides a compiled view is fresh by timestamp.** A mutation harness that
restores a source file with `cp -p` or `shutil.copy2` keeps the file's old mtime,
so the view compiled from the deliberately-broken version stays "newer" than the
source that replaced it and is reused. The suite then measures the mutation, and
the failure reads as the fix never having worked — one probe reported a card
counted twice on an untouched tree for exactly this reason. `php artisan
view:clear` before each run of such a harness.

## A session without a password

Anything behind the login gate needs a session to smoke-test, and the wrong trade
is handing a script an email and a password: credentials end up in environment
variables, in shell history and in logs, for a check that has nothing to do with
logging in. `tools/smoke-session.php` borrows a session instead. It asks the
application for a cookie exactly as a first-time visitor would, then rewrites the
row that cookie points at so it carries the login, and prints the cookie for
`tools/question-chat-smoke.mjs`. Three things about that are worth writing down,
because each one cost a debugging round and produced a failure that looked
exactly like a broken page:

1. **The session payload is base64 of a JSON object, not of `serialize()`.** PHP
   serialization is what the shape suggests and what Laravel itself used to
   write; a row written that way reads back as an *empty session*, which the
   application reports as "guest", which the browser reports as a redirect to
   `/masuk`. Settle it by reading a row the application wrote: decode it and the
   first characters are `{"_token"`.
2. **A cookie value is not just the session id.** `EncryptCookies` puts a
   per-cookie prefix in front of every value it encrypts
   (`CookieValuePrefix::create($name, $key)`) and hands the value on only if the
   prefix is there — the guard against one cookie's value being pasted into
   another. Encrypt that prefix along with the id, and ask
   `EncryptCookies::serialized($name)` rather than assuming the flag.
3. **A cookie jar escapes the base64 padding.** `%3D` at the end of a value read
   back from a cookie jar is not a character Laravel encrypted.
4. And the negative result that sent me looking: a probe test **cannot** answer
   this question. `phpunit.xml` sets `SESSION_DRIVER=array`, so a session row is
   never consulted and the probe fails whatever is wrong with it. The check only
   means something against the real host.

## Measure before rendering anything 110 times

Asking about one item was first built as a collapsed box inside every review row.
It was the obvious no-JavaScript shape and it was the wrong one: measured, it
added **597 kB** of markup to a page that was already 696 kB — 5.4 kB per item,
for 110 boxes almost nobody opens, paid on every load and mostly on phones. The
same feature as a link to the item's own page costs 104 kB, and the page it opens
has room for a conversation. Extracting the option row into a component while
removing it took another ~200 kB of whitespace out of the list.

The rule that generalises: before adding one more thing to a list of a hundred
and ten, render it and count the bytes. The number is never what the shape
suggests.

## Talking back to the analysis

The analysis panel used to be a wall of prose under a button. It is now a
conversation with the assistant that wrote it: the request that produced the
analysis is the participant's turn, the analysis itself is the assistant's
opening turn, and a composer sits under it for asking back. Enter sends,
Shift+Enter makes a line, the button stays.

The analysis is deliberately not stored as a message. It stays on
`attempt_analyses` where it already was, and the conversation holds only what came
after it — so the expensive text is not duplicated, and regenerating it (which
reuses the row in place) cannot leave the thread disagreeing with the text above
it. What a follow-up sends is the rules, the sitting's facts, the stored analysis
as the assistant's turn, and then the conversation, capped so a long one stays
bounded and never starting on an answer.

Both conversations — the one about an item, and the one about the sitting — print
their turns through the same component and name the assistant the same way
(`config('ai.bot_name')`), and Enter works in both through one module. There is
one description of what a turn looks like, and it lives in one file.

Which side a turn sits on is asserted in the browser, not in PHP: the suite counts
turns and reads their labels, and `tools/analysis-chat-smoke.mjs` measures the
bubbles against the thread's own box. A PHP test that counts a margin utility to
mean "on the right" is a test that breaks the next time somebody restyles the
bubble.

## Two silent failures worth knowing

**SQLite does not error on a column that does not exist.** A double-quoted
identifier in a where clause that matches no column is read as a string literal,
so the comparison quietly matches everything or nothing.
`whereNotNull('published_at')` against a table whose column is `status` matched
every row; `where('package_id', ...)` against a table whose column is
`exam_package_id` matched none. Both looked like working code, and the second is
why the smoke's seeder kept starting new papers. Assert the value a query
produced, not that the script reached its last line.

**A submitted attempt is frozen.** Answers are graded once, at submit, so
re-answering a finished attempt and grading it again changes nothing. The seeder
"answered" a paper that had already been handed in, reported success, and left the
analysis describing a different participant entirely. A fixture starts a new
sitting, or it edits the frozen columns on purpose. The tell was in the model's
own words: it kept explaining that a flawless 550/550 paper has no weaknesses.

## A filter that no longer reloads the page

The review list's tabs were ordinary links to `?filter=…`. That worked and cost
nothing — until one was pressed. A link is a document load, and a document load
starts at the top, so changing filter threw the reader out of a 110-row list they
were in the middle of and back up to the score cards.

Livewire would have fixed it, and it is the obvious answer. It is also the wrong
one here. Livewire renders by round-tripping to the server on every interaction,
and the largest piece of interactivity in this application is the exam room — 110
questions, on a phone, against a running clock. Livewire cannot go there. Adopting
it for the tabs would have bought one feature and paid for it with two
architectures in one codebase, one of which is barred from the part that matters
most. The whole of `resources/js/` is 789 lines, and the built bundle 10 kB.

So the tabs stayed links and gained one module. `review.js` fetches the same
address with `X-Review-Partial: 1`; `ResultController` answers with the review
card's body instead of the document; the module swaps it into the card. The card
itself is declared in `exam.result`, outside the partial, and is never replaced —
which is what lets every listener be bound once to it and outlive the swap.

Three consequences worth keeping:

- The server still decides what a filter contains, so the tabs work with scripting
  off and `?filter=` stays a shareable, reloadable address. The fragment is the
  same read as the page, behind the same ownership and submitted-sitting gates,
  which is why those gates are asserted again for the header case.
- The fragment is the body only. If the partial ever regrows its own `data-review`
  card, the module goes on listening to the outer one and every press nests a
  second list inside the first. `ResultPageTest` counts the cards for that reason.
- Focus is put back on the tab that is now current, with `preventScroll`. Without
  it the swap drops a keyboard user at the top of the document — the same failure
  this change exists to remove.

`tools/review-tabs-smoke.mjs` does the measuring, and it does not trust the markup:
it marks `window`, clicks, and reads the marker back to prove no document was
loaded, then compares the scroll offset and the card's position on screen. It
reported `scrollY 5779 → 5779` and a card top of `96.4 → 96.4`.

One trap in that script earns its own line. A Playwright `locator.click()` scrolls
its target into view when it is off screen, and that scroll lands in the "after"
measurement: the page looked like it had moved 5,000px when the test had done the
moving. The script now asserts the tab is on screen and un-covered *before* it
measures, so a future failure says which of the two happened.

## An affordance is not a permission

The admin panel was reachable and invisible at the same time. `/admin` has answered
200 for an administrator since it was built, and the middleware, the nav and the
screens all worked — but every `route('admin.*')` reference in the repository lived
inside `resources/views/admin/`, so an administrator's dashboard, header and footer
rendered exactly like a participant's. The only door was typing the URL, and from the
operator's chair that reads as "the admin account is the same as a normal user",
which is a real defect even though nothing was misauthorised.

The header's account cluster now switches on `auth()`. A guest keeps the marketing
CTAs unchanged — byte-identical, which is what keeps `BrandIdentityTest` and the
marketing pages out of this change — a member gets `Dashboard`, and an administrator
also gets `Panel admin`. Both carry `data-header-account="dashboard|admin"`, because
the assertions cannot be on the destination URL: the mega menu carries `/masuk` and
`/daftar` for guests on every page, so a URL assertion passes whether or not the
cluster switched. The old header test in `ExamRoomTest` was doing exactly that — it
asserted those two URLs while signed in and was green for the wrong reason.

An administrator also gets one card on the dashboard, and the panel gained
`Lihat situs peserta`. That second one is not decoration: the sidebar's user block was
`hidden … lg:block`, so a phone user had no log-out and no way back **before** this
change, and making the panel easy to enter without fixing the exit would have been a
trap of our own making.

### Selecting a responsive element by visibility

`document.querySelector` returns the first match in document order, and this header
keeps the desktop cluster and the phone cluster in the DOM at every width with only
one of them displayed. Selecting by selector alone measures the hidden one.

`tools/admin-entry-smoke.mjs` reported four failures on its first run — a zero-width
cluster "overlapping" the nav at 1440px, and an admin link that "was not visible" at
390px — and every one of them was the tool lying, not the page. The fix is to filter
on visibility before reading a box (`display !== 'none'` and a non-zero width) and to
assert the count of *visible* matches: `linkCount === 2` on a desktop and `1` on a
phone is the check that would have caught the whole class at once. The screen-wide
numbers are in the same run — at 992px, the tightest width where the desktop cluster
exists, the cluster still clears the nav with room to spare.

## A decoration is only visible against what is behind it

The homepage was carrying the same picture three times: an exam-room illustration in
the hero, the same room again in the "why" preview, and a phone showing a score card
in the closing band. The hero's and the band's are gone, so the product appears once.
Both were generated by `tools/build-brand-assets.mjs`, which is why that script lost
two builder functions rather than two files — deleting only the files would have let
the next run put them back.

Removing the hero's illustration did something nobody predicted, including the
person who asked for it: the four decorative props around it turned invisible. They
had been `#0657a8` on a white illustration; with the illustration gone they were
`#0657a8` on a `#0657a8` panel. A decorative prop's contrast is a property of what is
behind it, so a change to the background is a change to the art. They are `#ffffff`
now, and `docs/BRAND-ASSETS.md` records the exception, because the vendored-icon rule
there said one stroke colour for all of them.

The props also stopped being four absolutely positioned corners of an invisible
square and became one 2x2 grid. The old placement only worked because the square was
visible; without it, four icons at the corners of a 470px void read as leftovers.
Grid order comes from the content file, so the cluster is now edited there.

**Tuning it was measured, not guessed.** The cluster at 96px icons left the right half
of the panel empty; at 128px it crowded the copy and flipped the imbalance the other
way. 112px in a 440px cluster is what the screenshots settled on. Three screenshot
rounds is cheap; shipping "it looks fine to me" is not.

## Removing a badge is the same edit as removing the heading

The hero's tagline sat in a translucent capsule, and the capsule was the wrapper
around the page's only `<h1>`. Deleting the wrapper is exactly the edit that takes
the heading with it — so `HomepageTest` now asserts the `<h1>` on its own, and that
the homepage carries exactly one. Both new assertions were proved to have teeth by
mutation: downgrading the `<h1>` to a `<p>` fails that test and nothing else, adding
a nav item back fails only the nav test, and putting the band's badge back fails only
that one.

The same shape of trap applies to the nav. Two mega menus came out of the bar, but a
test cannot assert the word "Tryout" is gone: it is still a footer column heading and
a footer link. The assertion is on `data-menu-trigger` and `data-menu-panel` instead —
the dropdown is what left, not the word. The empty `navigation.items` in
`resources/content/site.php` keeps the mega-menu markup alive and unused, so bringing
an item back is a content-file entry rather than a view edit, which is the property
worth keeping.

## Counting capsules instead of grepping for them

"Hapus semua pill badge" cannot be answered by reading markup: `rounded-full` is on
buttons, on avatars, on progress bars and on the header itself. `tools/pill-scan.mjs`
(a throwaway) enumerated every element whose computed `border-radius` is at least half
its own height and that paints a background or a border and holds short text. On the
homepage that is eleven elements, and the classification falls out of the list:

- the header pill itself — the container, not a badge;
- `Daftar` and `Masuk` in the header, and again in the hero — real links;
- four `span`s inside the program cards: `Mulai Tryout`, `Lihat Analisa`,
  `Lihat Pembahasan`, `Buka Riwayat`;
- `GRATIS DICOBA` in the closing band, and `Daftar Gratis` beside it.

Two were badges and came out: the hero's tagline capsule and `GRATIS DICOBA`, which
restated the note under its own button ("Gratis • Tanpa kartu kredit") on a page that
already says free three times.

The four program-card labels stayed, and that is a judgement call worth writing down.
They look like buttons and are the only reason the question came up, but they are the
label *inside* an `<a>` that wraps the whole card — pressing one works, because
pressing anywhere on the card works. They are not dead affordances; they are the
card's action text. If they are not wanted, that is a design decision about the cards,
not a badge cleanup, so it is not something to settle silently here.

## An inline-level mark in a block anchor is never centred

"Logo-nya belum benar-benar di tengah, space di bawah ama di atasnya beda." Two
anchors wrapped the brand lockup and both were the same defect: `<a class="block">`
in the bar and `<a class="inline-block">` in the footer. The lockup is an
`inline-flex` span, so it sits on a text baseline, and the anchor's height became a
**line box** — the mark's 34px plus the font's descender space, 42.67px. The pill
centres that box, which put 10.11px above the mark and 18.78px below it: 8.67px of
asymmetry, and the same 8.67px in the footer, where the legal bar then started that
much lower than its own margin said.

`flex` on the anchor blockifies the mark, the height becomes the mark's own, and
both anchors now measure equal to their mark (34 and 36). The footer keeps `w-fit`
so the click target stays on the mark instead of becoming the whole 1140px row.

This is invisible to the PHP suite and to a screenshot review — the markup is
identical either way — so `tools/header-geometry.mjs` measures it at four widths and
fails above 1px of drift. Proved by mutation: restore `class="block"` and the tool
reports the original 8.67px.

## The box is centred; the ink is not

The fix above centres the mark's *box* to 0.01px. The report came back "still not
centred", and answering it needed a measurement rather than an opinion, because the
two candidates disagreed:

- vision, on the same screenshot, said the space below was bigger — then on a second
  run said the space above was bigger, "high confidence" both times;
- thresholded pixels said below was bigger by 2.5px;
- `path.getBoundingClientRect()` said 0.71px.

Only the third is free of judgement. The glyph is Tabler's cloud, whose ink spans
y 4..19 of its own 24-unit grid, so even in a perfectly centred box the cloud rides
0.5 units — 0.71px at 34px — high. The thresholded number was inflated by the
glyph's flat bottom edge: antialiasing spreads a flat edge over a faint row that a
threshold cuts, while the curved top keeps a sharper one, so the ink measured 2.5px
high when it is geometrically 0.71px high.

Conclusion: centred to within a pixel, which is below what the eye resolves, and the
remainder belongs to the icon's own geometry. Editing the viewBox of a vendored
Tabler glyph to chase 0.71px would also pull the cloud out of line with the wordmark,
which currently sits 0.48px from it, so it was left alone deliberately.

The lesson for next time: when a visual claim is at the pixel level, use
`getBoundingClientRect()` on the ink, not a thresholded screenshot, and treat vision
as a lead rather than as evidence — here it contradicted itself on the same image.
