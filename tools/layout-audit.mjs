// Measure layout defects in a rendered page instead of guessing at them.
//
// Usage: node tools/layout-audit.mjs <url> [more-urls...] [--width=1440,390] [--all] [--menus]
//   --all     also print the informational rows (decorative overlap).
//   --width=  comma-separated viewport widths; each is a fresh context.
//   --menus   also measure the page with the header's menu panels open, since a
//             floating panel over content is the classic stacked layout and it
//             does not exist in the page's default state.
//
// Several URLs share one browser on purpose: launching chromium once per page
// costs more than the measurement, and this host has no swap, so six parallel
// chrome processes is how a session gets killed.
//
// The measurement itself lives in tools/lib/layout-measure.mjs, shared with
// tools/authed-audit.mjs so both report the same numbers for the same terms.
//
// The host quirk that matters: a node script that falls off the end aborts with
// SIGABRT here, so the run always exits through process.exit().

import { chromium } from 'playwright-core';
import { countDefects, measureLayout, printReport, settlePage } from './lib/layout-measure.mjs';

const args = process.argv.slice(2);
const urls = args.filter((arg) => !arg.startsWith('--'));
const widths = (args.find((arg) => arg.startsWith('--width='))?.split('=')[1] ?? '1440')
  .split(',').map(Number).filter((value) => Number.isFinite(value) && value > 0);
const showAll = args.includes('--all');
const withMenus = args.includes('--menus');

if (urls.length === 0) urls.push('http://127.0.0.1:8123/');

const browser = await chromium.launch({
  executablePath: process.env.BROWSER_PATH
    ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome',
  args: ['--no-sandbox'],
});

/**
 * Open every collapsed disclosure the header owns. The phone layout hides the
 * same destinations behind a drawer with accordions, and the desktop layout
 * behind hover panels, so the widest viewport is not a superset of the narrow
 * one: both are opened, and whatever is not present is simply skipped.
 */
async function openEveryPanel(page) {
  const opened = await page.evaluate(() => {
    const names = [];
    const click = (el, label) => {
      if (!el) return;
      el.click();
      names.push(label);
    };

    click(document.querySelector('[data-drawer-toggle]'), 'drawer');
    for (const trigger of document.querySelectorAll('[data-menu-trigger]')) {
      click(trigger, `menu:${trigger.dataset.menuTrigger ?? trigger.textContent.trim().slice(0, 20)}`);
    }
    for (const accordion of document.querySelectorAll('[data-drawer-accordion]')) {
      click(accordion, `accordion:${accordion.textContent.trim().slice(0, 20)}`);
    }

    return names;
  });

  // The panel animations run on transitions; measuring mid-flight would report
  // the panel at a position it never rests at.
  await page.waitForTimeout(500);

  return opened;
}

let total = 0;

for (const width of widths) {
  const context = await browser.newContext({ viewport: { width, height: width < 768 ? 844 : 900 } });
  const page = await context.newPage();

  for (const url of urls) {
    // A page that fails to load must not abort the batch: the failing URL is the
    // finding, and a later one may be the page worth reading.
    try {
      const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
      await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});
      await settlePage(page);

      const states = [['default', null]];
      if (withMenus) states.push(['menus open', openEveryPanel]);

      for (const [state, prepare] of states) {
        if (prepare) await prepare(page);

        const report = await measureLayout(page);
        const defects = countDefects(report);
        total += defects;

        const label = `${url} @ ${width}px  [${response?.status() ?? 0}]  ${state}`;

        if (defects > 0 || showAll) printReport(label, report, { showAll });
        else console.log(`\n== ${label}  clean`);
      }
    } catch (error) {
      console.log(`\n== ${url} @ ${width}px  FAILED TO MEASURE: ${error.message}`);
    }
  }

  await context.close();
}

console.log(`\nTOTAL DEFECTS: ${total}`);

await browser.close();
process.exit(total === 0 ? 0 : 1);
