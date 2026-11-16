// Measures the account pages in a real browser: card geometry, the gap under
// the fixed header, computed control styling and the checkbox, at desktop and
// phone widths. Screenshots land next to the numbers so a regression is visible
// in the file names.
//
//   node tools/auth-visual.mjs [base-url] [out-dir]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const EXEC = `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;
const BASE = process.argv[2] ?? 'https://cpns.buatjalan.xyz';
const OUT = process.argv[3] ?? "/tmp/auth-visual";
const PAGES = [['login', '/masuk'], ['register', '/daftar']];
const VIEWPORTS = [['desktop', 1440, 1000], ['mobile', 390, 844]];

mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: EXEC, args: ['--no-sandbox'] });

for (const [vpName, width, height] of VIEWPORTS) {
  const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2 });
  const page = await context.newPage();
  for (const [name, path] of PAGES) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/${name}-${vpName}.png` });

    const m = await page.evaluate(() => {
      const r = (el) => { if (!el) return null; const b = el.getBoundingClientRect(); return { top: Math.round(b.top), bottom: Math.round(b.bottom), left: Math.round(b.left), right: Math.round(b.right), w: Math.round(b.width), h: Math.round(b.height) }; };
      const header = document.querySelector('[data-site-header] > div');
      const section = document.querySelector('main > section');
      const h1 = document.querySelector('main h1');
      const card = document.querySelector('main .rounded-card') ?? document.querySelector('main > section > div');
      const label = document.querySelector('main label span');
      const btn = document.querySelector('main button[type="submit"]');
      const footer = document.querySelector('footer');
      const cs = (el) => el ? getComputedStyle(el) : null;
      return {
        vh: window.innerHeight,
        header: r(header),
        section: r(section),
        h1: r(h1),
        card: r(card),
        label: r(label),
        button: r(btn),
        footerTop: footer ? Math.round(footer.getBoundingClientRect().top + window.scrollY) : null,
        docH: document.documentElement.scrollHeight,
        cardStyle: cs(card) ? { radius: cs(card).borderRadius, shadow: cs(card).boxShadow, pad: cs(card).padding, bg: cs(card).backgroundColor, border: cs(card).borderWidth + ' ' + cs(card).borderColor } : null,
        sectionPad: cs(section) ? cs(section).paddingTop + ' / ' + cs(section).paddingBottom : null,
        h1Size: cs(h1) ? cs(h1).fontSize + ' w' + cs(h1).fontWeight + ' ' + cs(h1).fontFamily.split(',')[0] : null,
        btnStyle: cs(btn) ? { radius: cs(btn).borderRadius, bg: cs(btn).backgroundColor, h: r(btn)?.h, fs: cs(btn).fontSize, weight: cs(btn).fontWeight } : null,
        inputStyle: (() => { const i = document.querySelector('main input[type=email]'); if (!i) return null; const c = getComputedStyle(i); return { radius: c.borderRadius, border: c.borderWidth + ' ' + c.borderStyle + ' ' + c.borderColor, pad: c.padding, fs: c.fontSize }; })(),
        checkbox: (() => { const c = document.querySelector('main input[type=checkbox]'); if (!c) return null; const g = getComputedStyle(c); return { w: r(c).w, h: r(c).h, appearance: g.appearance, accent: g.accentColor, border: g.borderWidth + ' ' + g.borderStyle + ' ' + g.borderColor, radius: g.borderRadius, bg: g.backgroundColor }; })(),
      };
    });
    const overlap = m.header && m.h1 ? Math.max(0, m.header.bottom - m.h1.top) : null;
    console.log(JSON.stringify({ name, vpName, ...m, headerOverlapsH1By: overlap }));
  }
  await context.close();
}
await browser.close();
process.exit(0);
