// Screenshot the marketing pages of a locally served SUPA CPNS build, so the
// rebrand can be judged in a real browser instead of from markup.
//
// Usage: node tools/page-shots.mjs <base-url> <output-dir> [path...]
//
// The host quirk that matters here: a node script that falls off the end aborts
// with SIGABRT on this machine, so the run always ends through process.exit().

import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright-core';
import { settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8123', outDir = '/tmp/page-shots', ...paths] = process.argv.slice(2);
const targets = paths.length > 0 ? paths : ['/'];

const viewports = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'phone', width: 390, height: 844 },
];

mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',
});

const problems = [];

for (const viewport of viewports) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('requestfailed', (request) => consoleErrors.push(`REQUEST FAILED ${request.url()}`));

  for (const path of targets) {
    const url = new URL(path, baseUrl).toString();
    const response = await page.goto(url, { waitUntil: 'networkidle' });

    // Load every lazy image and return to the top through the shared helper, so
    // a full-page capture is not taken while the page is still scrolling back.
    // The marketing pages declare `scroll-behavior: smooth`, and a capture taken
    // mid-scroll paints the fixed header at the offset it was passing, which then
    // reads in the image as the header lying on top of a section it never covers.
    await settlePage(page);

    const slug = (path === '/' ? 'home' : path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, ''));
    const file = `${outDir}/${slug}-${viewport.name}.png`;
    await page.screenshot({ path: file, fullPage: true });

    const report = await page.evaluate(() => {
      const broken = [...document.images]
        .filter((image) => !image.complete || image.naturalWidth === 0)
        .map((image) => image.currentSrc || image.src);

      const offSite = [...document.querySelectorAll('a[href]')]
        .map((anchor) => anchor.getAttribute('href'))
        .filter((href) => /^https?:\/\//.test(href) && !href.startsWith(location.origin));

      return {
        title: document.title,
        width: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        broken,
        offSite: [...new Set(offSite)],
        hasMark: /M10\.04 4\.305/.test(document.documentElement.outerHTML),
      };
    });

    const status = response ? response.status() : 0;
    const line = `${viewport.name} ${path} -> ${status} ${report.width}x${report.height} mark=${report.hasMark} broken=${report.broken.length} offsite=${report.offSite.length}`;
    console.log(line, '->', file);

    if (report.broken.length > 0) console.log('  broken images:', report.broken);
    if (report.offSite.length > 0) console.log('  off-site links:', report.offSite);
    if (consoleErrors.length > 0) console.log('  console:', [...new Set(consoleErrors)]);
    if (status !== 200 || report.broken.length > 0 || report.offSite.length > 0) problems.push(line);
  }

  await context.close();
}

await browser.close();

console.log(problems.length === 0 ? 'ALL PAGES CLEAN' : `PROBLEMS: ${problems.length}`);
process.exit(problems.length === 0 ? 0 : 1);
