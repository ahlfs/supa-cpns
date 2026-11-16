// Checks the administrator's way into the panel, in a real browser, at the
// widths where the header changes shape.
//
// The account cluster gained two pills where there used to be two, and the pill
// is only 1140px wide: at 992px — the width where the desktop cluster replaces
// the hamburger — the brand, the nav and the cluster now share a row that was
// already close to full. So this measures the row rather than trusting it, and
// drives both links so a dead href cannot hide behind a correct marker.
//
//   CHAT_SMOKE_COOKIE="$(SMOKE_EMAIL=you@example.com php artisan tinker \
//     --execute="require 'tools/smoke-session.php';" | tail -1)" \
//     node tools/admin-entry-smoke.mjs https://cpns.buatjalan.xyz [out-dir]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const EXEC = `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;
const BASE = process.argv[2] ?? 'https://cpns.buatjalan.xyz';
const OUT = process.argv[3] ?? '/tmp/admin-entry';
const COOKIE = process.env.CHAT_SMOKE_COOKIE ?? '';
const COOKIE_NAME = process.env.SMOKE_COOKIE_NAME ?? 'supa-cpns-session';

if (COOKIE === '') {
  console.error('CHAT_SMOKE_COOKIE is required; see the header of this file.');
  process.exit(2);
}

mkdirSync(OUT, { recursive: true });

let ok = 0;
let failed = 0;

const check = (label, pass, detail = '') => {
  if (pass) { ok += 1; console.log(`ok   ${label}`); }
  else { failed += 1; console.log(`FAIL ${label}${detail ? ` — ${detail}` : ''}`); }
};

const browser = await chromium.launch({ executablePath: EXEC, args: ['--no-sandbox'] });
const url = new URL(BASE);

// Widths: the desktop cluster only exists from 992px, and 992/1100 are where the
// row is tightest. 390 is the phone the row collapses to one pill on.
const WIDTHS = [[1440, 1000, 'desktop'], [1100, 900, 'laptop'], [992, 900, 'edge'], [390, 844, 'phone']];

for (const [width, height, name] of WIDTHS) {
  const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2 });
  await context.addCookies([{
    name: COOKIE_NAME, value: COOKIE, domain: url.hostname, path: '/', httpOnly: true, secure: true, sameSite: 'Lax',
  }]);
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

  await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });

  check(`${name}: /dashboard is 200 and not a redirect to login`, !page.url().includes('/masuk'), page.url());

  const row = await page.evaluate(() => {
    const box = (el) => { if (!el) return null; const b = el.getBoundingClientRect(); return { l: Math.round(b.left), r: Math.round(b.right), t: Math.round(b.top), b: Math.round(b.bottom), w: Math.round(b.width), h: Math.round(b.height) }; };
    const pill = document.querySelector('[data-site-header] > div');
    const nav = document.querySelector('[data-site-header] nav');
    const shows = (el) => !!el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().width > 0;

    // Both clusters are always in the DOM and only one is ever displayed, so the
    // links have to be picked by visibility. Picking the first match returns the
    // desktop cluster on a phone, which is display:none and measures as zero.
    const links = [...document.querySelectorAll('[data-site-header] [data-header-account]')];
    const pick = (kind) => links.find((el) => el.dataset.headerAccount === kind && shows(el)) ?? null;
    const visible = links.filter(shows);

    const admin = pick('admin');
    const dash = pick('dashboard');

    // The cluster as the reader sees it: the union of the links that are actually
    // on screen.
    const boxes = visible.map((el) => el.getBoundingClientRect());
    const cluster = boxes.length === 0 ? null : {
      l: Math.round(Math.min(...boxes.map((b) => b.left))),
      r: Math.round(Math.max(...boxes.map((b) => b.right))),
    };

    return {
      pill: box(pill),
      cluster,
      nav: shows(nav) ? box(nav) : null,
      admin: admin ? { ...box(admin), href: admin.getAttribute('href'), text: admin.innerText.trim() } : null,
      dash: dash ? { ...box(dash), href: dash.getAttribute('href'), text: dash.innerText.trim() } : null,
      linkCount: visible.length,
      badge: document.body.innerText.includes('Buka panel'),
    };
  });

  const wide = width >= 992;
  check(`${name}: the panel link renders`, row.admin !== null);
  check(`${name}: the panel link is the only visible account link on a phone or one of two on a desktop`,
    wide ? row.linkCount === 2 : row.linkCount === 1, `visible links=${row.linkCount}`);
  check(`${name}: the panel link points at /admin`, row.admin?.href === '/admin', row.admin?.href ?? 'missing');

  if (wide) {
    check(`${name}: the dashboard link is visible too`, row.dash !== null, 'no visible dashboard link');
    check(`${name}: the cluster does not overlap the nav`,
      row.nav === null || (row.cluster?.l ?? 0) >= row.nav.r,
      `cluster.l=${row.cluster?.l} nav.r=${row.nav?.r}`);
    check(`${name}: the cluster fits inside the pill`, (row.cluster?.r ?? Infinity) <= (row.pill?.r ?? 0),
      `cluster.r=${row.cluster?.r} pill.r=${row.pill?.r}`);
    check(`${name}: the pills sit on one line`, row.admin && row.dash ? Math.abs(row.admin.t - row.dash.t) <= 2 : true,
      `admin.t=${row.admin?.t} dash.t=${row.dash?.t}`);
    check(`${name}: the pill keeps its height`, (row.pill?.h ?? 0) >= 60 && (row.pill?.h ?? 0) <= 70, `h=${row.pill?.h}`);
    check(`${name}: both pills stay inside the pill row`, (row.dash?.r ?? 0) <= (row.pill?.r ?? 0)
      && (row.admin?.l ?? 0) >= (row.pill?.l ?? 0), `dash.r=${row.dash?.r} admin.l=${row.admin?.l}`);
  } else {
    // One pill only on the phone: the admin one, with both destinations in the
    // drawer. A second pill here is what would push the row over.
    check(`${name}: the dashboard link is not in the phone bar`, row.dash === null, `dash=${row.dash?.text}`);

    await page.click('[data-drawer-toggle]');
    await page.waitForTimeout(250);
    const drawer = await page.evaluate(() => {
      const d = document.querySelector('[data-mobile-drawer]');
      const vis = (el) => !!el && el.getBoundingClientRect().width > 0 && getComputedStyle(el).display !== 'none';
      return {
        open: vis(d),
        admin: vis(d?.querySelector('[data-header-account="admin"]')),
        dash: vis(d?.querySelector('[data-header-account="dashboard"]')),
      };
    });
    check(`${name}: the drawer opens`, drawer.open === true);
    check(`${name}: the drawer carries both destinations`, drawer.admin === true && drawer.dash === true,
      `admin=${drawer.admin} dash=${drawer.dash}`);
  }

  check(`${name}: the dashboard announces the panel`, row.badge === true);
  check(`${name}: no console errors`, consoleErrors.length === 0, consoleErrors.slice(0, 2).join(' | '));

  await page.screenshot({ path: `${OUT}/dashboard-${name}.png` });
  await context.close();
}

// Drive both links at desktop width: a marker proves the anchor exists, not that
// it goes anywhere.
{
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 });
  await context.addCookies([{
    name: COOKIE_NAME, value: COOKIE, domain: url.hostname, path: '/', httpOnly: true, secure: true, sameSite: 'Lax',
  }]);
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

  await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
  await page.click('[data-header-account="admin"]');
  await page.waitForLoadState('networkidle');

  check('clicking the panel link lands on the panel', page.url().endsWith('/admin'), page.url());
  check('the panel shows its screens', await page.locator('text=Ringkasan').first().isVisible());

  await page.screenshot({ path: `${OUT}/admin-panel.png` });

  await page.click('text=Lihat situs peserta');
  await page.waitForLoadState('networkidle');

  check('the panel sends you back to the participant site', page.url().endsWith('/dashboard'), page.url());
  check('no console errors crossing the panel', consoleErrors.length === 0, consoleErrors.slice(0, 2).join(' | '));

  await context.close();
}

await browser.close();

console.log(`\n${ok} ok, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
