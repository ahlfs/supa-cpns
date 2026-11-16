// Measure the header pill's geometry in a real browser: is the brand mark
// actually centred in the bar, and what does the bar actually offer.
//
// The complaint this answers — "the logo's space above and below is different" —
// is invisible to a screenshot review and invisible to the PHP suite: the markup
// is identical either way, and a few pixels of asymmetry read as "slightly off"
// without anyone being able to say by how much. So it is measured.
//
// Why it happened at all: the mark is an inline-level flex box (`inline-flex`)
// inside a block anchor. An inline-level box sits on a text baseline, so the
// anchor's height was a LINE BOX — the mark's 34px plus the font's descender
// space, 42.67px in total — and the pill centred that taller box instead of the
// mark. The fix is to stop the anchor being a line box (make it a flex
// container), and this tool is what proves which of the two is on the page.
//
//   node tools/header-geometry.mjs [url] [out-dir]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const EXEC = process.env.BROWSER_PATH ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;
const BASE = (process.argv[2] ?? 'https://cpns.buatjalan.xyz').replace(/\/$/, '');
const OUT = process.argv[3] ?? '/tmp/header-geometry';

mkdirSync(OUT, { recursive: true });

let ok = 0;
let failed = 0;

const check = (label, pass, detail = '') => {
  if (pass) { ok += 1; console.log(`ok   ${label}`); }
  else { failed += 1; console.log(`FAIL ${label}${detail ? ` — ${detail}` : ''}`); }
};

// 992 is the width where the desktop cluster appears and the row is tightest;
// 390 is the phone bar. 600 is the documented boundary of the mobile tier.
const WIDTHS = [[1440, 900, 'desktop'], [992, 900, 'edge'], [600, 900, 'small'], [390, 844, 'phone']];

const browser = await chromium.launch({ executablePath: EXEC, args: ['--no-sandbox'] });

for (const [width, height, name] of WIDTHS) {
  const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2 });
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

  await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});

  const report = await page.evaluate(() => {
    const round = (value) => Math.round(value * 100) / 100;
    const box = (el) => {
      if (!el) return null;
      const b = el.getBoundingClientRect();
      return { top: round(b.top), bottom: round(b.bottom), left: round(b.left), right: round(b.right), height: round(b.height), width: round(b.width) };
    };
    const shows = (el) => getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().width > 0;

    const pill = document.querySelector('[data-site-header] > div');
    const brand = document.querySelector('[data-header-brand]');
    const mark = brand?.querySelector('svg') ?? null;

    /*
     * The ink, not the box. The glyph's own 24-grid does not centre it — Tabler's
     * cloud spans y 4..19 — so a box-centred mark still shows the cloud riding
     * ~0.7px high. Measuring the path's own rect is the claim a person actually
     * makes when they say the logo is off-centre, and it is what caught the
     * original defect (5.17px then, 0.71px now).
     */
    const ink = brand?.querySelector('path')?.getBoundingClientRect() ?? null;
    const pillRect = pill?.getBoundingClientRect() ?? null;
    const pillBorder = pill === null ? null : {
      top: Number.parseFloat(getComputedStyle(pill).borderTopWidth),
      bottom: Number.parseFloat(getComputedStyle(pill).borderBottomWidth),
    };

    // The footer mark is the same lockup in a different anchor; its height is the
    // proof that the anchor is not a line box.
    const footerAnchor = document.querySelector('[data-section="footer"] a');
    const footerMark = footerAnchor?.querySelector('svg') ?? null;

    /*
     * The bar's own entries, read from the `<nav>` when there is one. Addressing
     * them this way rather than by scanning the pill keeps the guest CTA pills
     * ("Masuk", "Daftar") out of the list: they are the account cluster, not
     * navigation, and counting them would let a stray nav link hide behind them.
     * The pill-wide scan stays as a fallback for a bar that renders no <nav>.
     */
    const nav = document.querySelector('[data-site-header] nav');
    const navLabels = nav === null
      ? [...(pill?.querySelectorAll('a') ?? [])]
        .filter((a) => !a.hasAttribute('data-header-brand') && !a.hasAttribute('data-header-account') && shows(a))
        .map((a) => a.textContent.trim())
      : [...nav.querySelectorAll('a')].filter(shows).map((a) => a.textContent.trim());

    return {
      pill: box(pill),
      pillAnchor: pill === null ? null : getComputedStyle(pill).alignItems,
      pillBorder,
      brand: box(brand),
      brandDisplay: brand === null ? null : getComputedStyle(brand).display,
      mark: box(mark),
      // How far the mark's ink sits from the pill's padding-box centre. Positive
      // means the ink hangs low.
      inkOffset: ink === null || pillRect === null || pillBorder === null
        ? null
        : Math.round(((ink.top + ink.bottom) / 2 - (pillRect.top + pillBorder.top + pillRect.bottom - pillBorder.bottom) / 2) * 100) / 100,
      inkHeight: ink === null ? null : Math.round(ink.height * 100) / 100,
      footerAnchor: footerAnchor === null ? null : {
        display: getComputedStyle(footerAnchor).display,
        height: Math.round(footerAnchor.getBoundingClientRect().height * 100) / 100,
        markHeight: footerMark === null ? null : Math.round(footerMark.getBoundingClientRect().height * 100) / 100,
      },
      navLabels,
      accountLabels: [...document.querySelectorAll('[data-site-header] [data-header-account]')].filter(shows).map((el) => el.textContent.trim()),
      hasNavElement: nav !== null,
    };
  });

  if (report.pill === null || report.mark === null) {
    check(`${name}: the header renders a pill with a brand mark`, false, JSON.stringify(report));
    await context.close();
    continue;
  }

  /*
   * The mark's edges against the pill's padding box. The pill carries a border,
   * so the padding box — not the border box — is what the flex centring works
   * inside; measuring against the border box would fold a 1px hairline into the
   * result and report a 1px asymmetry on a perfectly centred bar.
   */
  const innerTop = report.pill.top + report.pillBorder.top;
  const innerBottom = report.pill.bottom - report.pillBorder.bottom;
  const above = Math.round((report.mark.top - innerTop) * 100) / 100;
  const below = Math.round((innerBottom - report.mark.bottom) * 100) / 100;
  const drift = Math.round(Math.abs(above - below) * 100) / 100;

  console.log(`\n== ${name} (${width}px)  pill ${report.pill.width}x${report.pill.height} @${report.pill.top}, align-items=${report.pillAnchor}`);
  console.log(`   brand anchor display=${report.brandDisplay}  box ${report.brand.width}x${report.brand.height} @${report.brand.top}`);
  console.log(`   mark             box ${report.mark.width}x${report.mark.height} @${report.mark.top}`);
  console.log(`   box   space above ${above}px / below ${below}px  (drift ${drift}px)`);
  console.log(`   ink   centre ${report.inkOffset}px from the pill centre, ink ${report.inkHeight}px tall`);
  console.log(`   bar entries: ${JSON.stringify(report.navLabels)}   nav element: ${report.hasNavElement}`);
  console.log(`   account links: ${JSON.stringify(report.accountLabels)}`);
  console.log(`   footer anchor display=${report.footerAnchor?.display} ${report.footerAnchor?.height}px vs mark ${report.footerAnchor?.markHeight}px`);

  // One pixel of slack: sub-pixel layout rounds differently per zoom factor.
  check(`${name}: the brand mark's box is centred in the pill (above ${above}px vs below ${below}px)`, drift <= 1, `drift ${drift}px`);
  /*
   * The claim a person makes. The glyph's own grid is asymmetric — Tabler's cloud
   * spans y 4..19 of 24 — so a box-centred mark still shows the cloud 0.71px high;
   * the original defect was 5.17px, and the threshold is set between the two.
   */
  check(`${name}: the mark's ink is centred in the pill (${report.inkOffset}px off)`, Math.abs(report.inkOffset) <= 1, `offset ${report.inkOffset}px`);
  check(`${name}: the brand anchor is not a line box`, report.brandDisplay === 'flex' || report.brandDisplay === 'grid', `display=${report.brandDisplay}`);
  check(`${name}: the brand anchor is no taller than the mark`, report.brand.height <= report.mark.height + 0.5, `anchor ${report.brand.height} vs mark ${report.mark.height}`);
  check(`${name}: the bar carries no Beranda entry (the mark is the way home)`, !report.navLabels.includes('Beranda'), JSON.stringify(report.navLabels));
  check(`${name}: the footer mark's anchor is not a line box either`,
    report.footerAnchor !== null && report.footerAnchor.height <= report.footerAnchor.markHeight + 0.5,
    `anchor ${report.footerAnchor?.height}px vs mark ${report.footerAnchor?.markHeight}px`);

  // The drawer is the phone bar's menu, so its links are part of the same claim.
  if (width < 992) {
    await page.click('[data-drawer-toggle]');
    await page.waitForTimeout(250);

    const drawer = await page.evaluate(() => {
      const node = document.querySelector('[data-mobile-drawer]');
      // The account block is the drawer's child that is not the link list. It is
      // addressed structurally because a guest's block renders the login and
      // register pills, which carry no data-header-account marker.
      const block = [...node.children].find((child) => child.tagName !== 'UL');
      return {
        open: node.hidden === false,
        hasList: node.querySelector(':scope > ul') !== null,
        listLinks: [...(node.querySelector(':scope > ul')?.querySelectorAll('a') ?? [])].map((a) => a.textContent.trim()),
        links: [...node.querySelectorAll('a')].map((a) => a.textContent.trim()),
        separatorWidth: block === undefined ? null : getComputedStyle(block).borderTopWidth,
      };
    });

    check(`${name}: the drawer opens`, drawer.open === true);
    check(`${name}: the drawer carries no Beranda entry either`, !drawer.links.includes('Beranda'), JSON.stringify(drawer.links));
    check(`${name}: the drawer renders no empty link list`, (drawer.hasList && drawer.listLinks.length > 0) || !drawer.hasList,
      `hasList=${drawer.hasList} links=${JSON.stringify(drawer.listLinks)}`);
    /*
     * The account block's separator belongs to the link list above it. A
     * border-top over nothing reads as a stray hairline at the top of the drawer,
     * so the rule has to track the list rather than sit unconditionally.
     */
    check(`${name}: the separator follows the list it separates`,
      drawer.hasList ? drawer.separatorWidth !== '0px' : drawer.separatorWidth === '0px',
      `hasList=${drawer.hasList} separator=${drawer.separatorWidth}`);

    await page.screenshot({ path: `${OUT}/drawer-${name}.png`, clip: { x: 0, y: 0, width, height: Math.min(height, 420) } });

    await page.click('[data-drawer-toggle]');
    await page.waitForTimeout(200);
  }

  check(`${name}: no console errors`, consoleErrors.length === 0, consoleErrors.slice(0, 2).join(' | '));

  await page.screenshot({ path: `${OUT}/header-${name}.png`, clip: { x: 0, y: 0, width, height: Math.min(height, 260) } });
  await context.close();
}

await browser.close();

console.log(`\n${ok} ok, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
