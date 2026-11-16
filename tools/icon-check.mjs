// Checks that every SVG in resources/icons renders without the browser complaining.
//
// A malformed path does not fail any PHP test: the file is well-formed XML, Blade
// inlines it happily, and `<x-icon>` never looks at the geometry. What it does do is
// log "attribute d: Expected number" in the console and draw a broken or invisible
// glyph — and the message does not name the element, so this loads each file on its
// own page and reports the ones that complain.
//
// Usage: node tools/icon-check.mjs
//
// The browser binary comes from BROWSER_PATH, the same variable every other script
// in tools/ reads; CHROME_PATH is honoured as a fallback.
import { chromium } from 'playwright-core';
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dir = join(root, 'resources', 'icons');
const BROWSER = process.env.BROWSER_PATH
  ?? process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const files = readdirSync(dir).filter((file) => file.endsWith('.svg')).sort();
const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const broken = [];

for (const name of files) {
  const page = await browser.newPage();
  const errors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  await page.setContent(`<body style="margin:0">${readFileSync(join(dir, name), 'utf8')}</body>`);
  await page.waitForTimeout(100);

  if (errors.length > 0) broken.push({ name, errors: errors.slice(0, 2) });

  await page.close();
}

await browser.close();

for (const entry of broken) {
  console.log(`BROKEN ${entry.name}`);
  for (const error of entry.errors) console.log(`   ${error.slice(0, 160)}`);
}

console.log(`${broken.length} ikon bermasalah dari ${files.length}`);
process.exit(broken.length === 0 ? 0 : 1);
