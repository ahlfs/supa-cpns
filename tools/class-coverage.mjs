// Answers one question for a live page: does the stylesheet actually define a
// rule for every class the page uses? A stale build shows up here as classes
// that appear in the DOM but nowhere in the CSS.
//
//   node tools/class-coverage.mjs https://cpns.buatjalan.xyz / /masuk
import { chromium } from 'playwright-core';

const EXEC = `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;
const base = process.argv[2] ?? 'https://cpns.buatjalan.xyz';
const paths = process.argv.slice(3);

// Set COVERAGE_EMAIL / COVERAGE_PASSWORD to check pages behind a sign-in. The
// values come from the environment so no credential is written down here.
const email = process.env.COVERAGE_EMAIL ?? '';
const password = process.env.COVERAGE_PASSWORD ?? '';

const browser = await chromium.launch({ executablePath: EXEC, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

if (email !== '') {
  await page.goto(`${base}/masuk`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.startsWith('/masuk')).catch(() => {});
}

for (const path of paths) {
  await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);

  const report = await page.evaluate(() => {
    // Every class name the loaded stylesheets define, unescaped. Tailwind wraps
    // its output in @layer blocks, so the rules that carry classes are nested
    // inside layer (and media) blocks and have to be walked, not read off the
    // top level.
    const defined = new Set();
    const walk = (rules) => {
      for (const rule of rules) {
        if (rule.selectorText) {
          for (const m of rule.selectorText.matchAll(/\.((?:\\.|[\w-])+)/g)) {
            defined.add(m[1].replace(/\\(.)/g, '$1'));
          }
        }
        if (rule.cssRules) walk(rule.cssRules);
      }
    };
    for (const sheet of document.styleSheets) {
      try { walk(sheet.cssRules); } catch { /* cross-origin sheet: skip */ }
    }

    const used = new Set();
    for (const el of document.querySelectorAll('*')) {
      for (const cls of el.classList) used.add(cls);
    }

    const missing = [...used].filter((c) => !defined.has(c) && !c.includes('{{')).sort();
    return { defined: defined.size, used: used.size, missing };
  });

  console.log(`${path}: ${report.used} classes in the DOM, ${report.defined} class rules in CSS`);
  if (report.missing.length) {
    console.log(`   NOT DEFINED (${report.missing.length}):`, JSON.stringify(report.missing.slice(0, 25)));
  } else {
    console.log('   every class in the DOM has a rule');
  }
}

await browser.close();
process.exit(0);
