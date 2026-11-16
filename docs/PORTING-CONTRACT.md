# Porting contract — converting the reference page into Blade + Tailwind

The reference is a captured marketing page kept outside this repository. This app
is a Laravel rebuild of it: the design stays, the implementation becomes ours.

Every section is ported by working from **measurements, not guesses**:

- `.probe/computed/<section>-<width>.json` — the computed style and geometry of
  every element in that section, captured in a browser at 1440 / 768 / 390.
  Query it with
  `node /home/ahlfs/workspace/.probe/q.mjs <section> <width> '<regex>' [--depth N]`.
- `.probe/sections/<section>.html` — the reference markup for the section.
- `.probe/sections/<section>.css` — the reference rules that apply to it.

## Rules

1. **Tailwind utilities only.** No new CSS files, no BEM class names, no
   `<style>` blocks. If a value is not a stock utility, use the arbitrary value
   form (`h-[52px]`, `text-[#69727d]`, `shadow-[0_4px_16px_#07396c0a]`) or a
   token from `resources/css/app.css`. Ask before editing `app.css`.
2. **Content lives in `resources/content/home/<section>.php`.** The partial reads
   its data and prints it. No marketing copy, URL or image path written inline in
   Blade — if it is visible on the page, it comes from the content file.
3. **Images go through `<x-ui.image :image="$item['image']" />`.** Content stores
   `path`, `extension`, `widths`, `sizes`, `alt`, `width`, `height`; the component
   builds the srcset. Copy the variant widths from the reference markup.
4. **Brand strings use the content value**, e.g. `{{ $site['brand'] }}`, never a
   hardcoded "SUPA CPNS" or the reference brand.
5. **Every section root carries `data-section="<name>"`** — the tests and the
   browser tools find sections by it. Never move or rename it.
6. **Behaviour hooks are data attributes**, not classes:
   `[data-site-header]`, `[data-scrolled]`, `[data-nav-item]`,
   `[data-menu-trigger]`, `[data-menu-panel]`, `[data-drawer-toggle]`,
   `[data-mobile-drawer]`, `[data-drawer-accordion]`, `[data-drawer-sublist]`,
   `[data-program-tabs]`, `[data-tab]`, `[data-program-panel]`,
   `[data-carousel="<name>"]`, `[data-carousel-track]`, `[data-carousel-slide]`,
   `[data-carousel-dots]`, `[data-carousel-prev]`, `[data-carousel-next]`,
   `[data-back-to-top]`.
   State that changes the look is a `data-*` value styled with a Tailwind data
   variant (`data-[scrolled=true]:shadow-lg`), never a class toggled at runtime.
7. **Icons are real SVG.** Use `<x-icon name="…" class="h-4 w-4" />` with a name
   from `resources/icons/`; add the file if it does not exist. Never an emoji or
   an icon font, never a remote URL.
8. **Keep the accessibility of the reference**: the same `aria-*`, roles, alt
   text, `<h1>`/`<h2>` order and `tabindex` behaviour. Restore keyboard support
   where the reference had it.
9. **Copy the reference's responsive behaviour at the same breakpoints.** The
   reference uses max-width queries at 640 / 768 / 991 / 480; use Tailwind's
   `max-sm:` (< 640), `max-md:` (< 768), `max-lg:` (< 992) and `max-[480px]:`
   variants, or mobile-first equivalents, so the collapsed layout matches.
10. **No `style="…"` attributes** except for dynamic values JS must set (the
    carousel track width/transform are set by JS — that is expected).
11. **One variant per property, and only the largest one.** Never put two
    overlapping min-width variants (`md:` and `lg:`, say) on the same property:
    Tailwind decides their order, not the class attribute. Correct: base value +
    `lg:` override, plus a `max-sm:` override for phones — `max-sm:` and `lg:`
    never apply at the same width, so the result is deterministic. Verify with
    `tools/probe-breakpoints.mjs` that each property has exactly one boundary.

## Definition of done for a section

- `resources/content/home/<section>.php` holds all copy, links and images.
- `resources/views/sections/<section>.blade.php` renders it with Tailwind
  utilities, `data-section`, the behaviour hooks and real SVG icons.
- `npm run build` succeeds and `php artisan test --filter=HomepageTest` passes.
- `node tools/page-shots.mjs http://127.0.0.1:8000 / /masuk /daftar` reports no
  broken image, no off-site link and no console error at 1440 or 390, and no
  geometry drift over 2px from the measurement the section was built against.
