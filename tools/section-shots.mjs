// Screenshot one viewport-sized frame per section, instead of one full-page
// image.
//
// A full-page screenshot renders a `position: fixed` header at whatever offset
// the capture happened to freeze, so the header lands in the middle of the page
// and a later reading of that image reports the header as "stacked on top of"
// a section it never touches in a real browser. Scrolling to each section and
// capturing the viewport shows what a visitor actually sees, with the fixed
// layers where they belong.
//
// Usage: node tools/section-shots.mjs <base-url> <out-dir> <path> [width] [section...]
//
// The host quirk that matters: a node script that falls off the end aborts with
// SIGABRT here, so the run always exits through process.exit().

import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright-core';
import { settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8123', outDir = '/tmp/section-shots', path = '/', widthArg = '1440', ...wanted] = process.argv.slice(2);
const width = Number(widthArg);

mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: process.env.BROWSER_PATH
    ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',
  args: ['--no-sandbox'],
});

const page = await browser.newPage({ viewport: { width, height: width < 768 ? 844 : 900 } });
await page.goto(new URL(path, baseUrl).toString(), { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});
await settlePage(page);

// Section names in document order, so a caller can omit them and get them all.
const names = await page.evaluate(() => [...document.querySelectorAll('[data-section]')]
  .filter((el) => ['static', 'relative'].includes(getComputedStyle(el).position))
  .map((el) => el.dataset.section));

const targets = wanted.length > 0 ? wanted : names;
const slug = path === '/' ? 'home' : path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');

for (const name of targets) {
  const found = await page.evaluate((wantedName) => {
    const el = document.querySelector(`[data-section="${wantedName}"]`);
    if (!el) return null;
    // Land the section's top just below the fixed header strip, which is what a
    // reader following the page would see.
    const header = document.querySelector('header');
    const offset = header ? header.getBoundingClientRect().height + 12 : 0;
    window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'instant' });
    return el.getBoundingClientRect().top;
  }, name);

  if (found === null) {
    console.log(`missing section: ${name}`);
    continue;
  }

  await page.waitForTimeout(250);

  const file = `${outDir}/${slug}-${width}-${name}.png`;
  await page.screenshot({ path: file });
  console.log(`${name} -> ${file}`);
}

await browser.close();
process.exit(0);
