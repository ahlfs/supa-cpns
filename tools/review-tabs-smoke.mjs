// Filtering the review list, in a real browser, against the real page.
//
// The complaint this script exists to answer is not "the filter is wrong" — it is
// that pressing a tab threw the reader back to the top of a 110-row page. So the
// measurements here are about the document and the reader's place in it: whether a
// load happened at all, whether the card moved on screen, and whether the address
// bar, the Back button and a scripting-off browser still agree.
//
// Nothing here writes: filtering only reads. It signs in as an account that already
// has a finished sitting, borrowed through tools/smoke-session.php so no password
// is ever handled.
//
// Usage:
//   SMOKE_COOKIE="$(php artisan tinker --execute="require 'tools/smoke-session.php';" | tail -1)" \
//     node tools/review-tabs-smoke.mjs https://cpns.buatjalan.xyz 13
import { chromium } from 'playwright-core';
import { countDefects, measureLayout, printReport, settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8000', attemptId = ''] = process.argv.slice(2);

const BROWSER = process.env.BROWSER_PATH
  ?? process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const cookie = process.env.SMOKE_COOKIE ?? process.env.CHAT_SMOKE_COOKIE ?? '';
const cookieName = process.env.SMOKE_COOKIE_NAME ?? 'supa-cpns-session';

const FRAGMENT_HEADER = 'X-Review-Partial';

const results = [];

const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};

if (cookie === '' || attemptId === '') {
  console.error('Set SMOKE_COOKIE and pass an attempt id.');
  process.exit(2);
}

const resultUrl = `${baseUrl}/hasil/${attemptId}`;

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 390, height: 844 } });

await context.addCookies([{
  name: cookieName,
  value: cookie,
  domain: new URL(baseUrl).hostname,
  path: '/',
  httpOnly: true,
  secure: baseUrl.startsWith('https'),
}]);

const page = await context.newPage();
const consoleErrors = [];

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push(String(error)));

try {
  // What the server sends when a tab asks for a fragment. Counted straight off the
  // wire so the browser's list is compared against the server's answer rather than
  // against a number written here.
  const fragmentRows = async (filter) => {
    const response = await fetch(`${resultUrl}?filter=${filter}`, {
      headers: { [FRAGMENT_HEADER]: '1', Cookie: `${cookieName}=${cookie}` },
    });
    const body = await response.text();

    return {
      status: response.status,
      rows: (body.match(/<details/g) ?? []).length,
      document: body.includes('<html'),
    };
  };

  await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'domcontentloaded' });
  check('the injected session is accepted', !page.url().includes('/masuk'), page.url());

  if (page.url().includes('/masuk')) throw new Error('not signed in');

  const wrong = await fragmentRows('wrong');

  check('the fragment endpoint answers without a document', wrong.status === 200 && !wrong.document, `${wrong.status}, document=${wrong.document}`);
  check('the fragment is narrowed by the filter it was asked for', wrong.rows > 0 && wrong.rows < 110, `${wrong.rows} of 110 rows`);

  await page.goto(resultUrl, { waitUntil: 'domcontentloaded' });
  await settlePage(page);

  const rows = page.locator('[data-review-item]');

  check('the unfiltered page carries the whole review', (await rows.count()) === 110, `${await rows.count()} rows`);

  // Put the reader where the complaint was: down at the review list, with the tab
  // they are about to press actually on screen. The click has to be reachable
  // without Playwright scrolling to it — a locator click scrolls the target into
  // view itself, and that scroll would land in the "after" measurement and read as
  // the page having moved when it was the test that moved it. `instant` because
  // this page sets `scroll-behavior: smooth`, which makes a measurement taken right
  // after a scroll describe a position the page is still travelling away from.
  await page.evaluate(() => {
    const card = document.querySelector('[data-review]');
    const top = card.getBoundingClientRect().top + window.scrollY;

    window.scrollTo({ top: Math.max(0, top - 96), behavior: 'instant' });
  });

  const tab = page.locator('[data-review-tab][href*="filter=wrong"]');

  const tabReachable = await tab.evaluate((node) => {
    const box = node.getBoundingClientRect();

    return {
      clear: box.top > 0 && box.bottom < window.innerHeight,
      hit: document.elementFromPoint((box.left + box.right) / 2, (box.top + box.bottom) / 2)?.closest('[data-review-tab]') === node,
      top: box.top,
    };
  });

  check(
    'the tab to press is on screen and not covered, so clicking cannot scroll',
    tabReachable.clear && tabReachable.hit,
    `top=${tabReachable.top.toFixed(1)} clear=${tabReachable.clear} hit=${tabReachable.hit}`,
  );

  const before = await page.evaluate(() => ({
    scrollY: window.scrollY,
    top: document.querySelector('[data-review]').getBoundingClientRect().top,
  }));

  check('the reader is scrolled away from the top', before.scrollY > 0, `scrollY=${before.scrollY}`);

  // A marker on `window` only survives if no document was loaded. This is what
  // separates "the list was swapped" from "the browser rebuilt the page", which is
  // the difference the whole change is about.
  await page.evaluate(() => { window.__reviewSmoke = 'same document'; });

  await tab.click();
  await page.waitForURL('**filter=wrong', { timeout: 15000 });
  await page.waitForFunction(
    (expected) => document.querySelectorAll('[data-review-item]').length === expected,
    wrong.rows,
    { timeout: 15000 },
  );

  const after = await page.evaluate(() => ({
    marker: window.__reviewSmoke ?? null,
    scrollY: window.scrollY,
    top: document.querySelector('[data-review]').getBoundingClientRect().top,
    marked: document.querySelector('[data-review-tab][aria-current="page"]')?.getAttribute('href') ?? '',
    focused: document.activeElement?.getAttribute('href') ?? '',
  }));

  check('no document was loaded', after.marker === 'same document', `marker=${after.marker}`);
  check('the page did not jump to the top', after.scrollY > 0, `scrollY ${before.scrollY} → ${after.scrollY}`);
  check(
    'the card did not move on screen',
    Math.abs(after.top - before.top) <= 4,
    `top ${before.top.toFixed(1)} → ${after.top.toFixed(1)}`,
  );
  check('the address bar carries the filter', page.url().includes('filter=wrong'), page.url());
  check(
    'the list is what the server would have sent',
    (await rows.count()) === wrong.rows,
    `${await rows.count()} rows, server said ${wrong.rows}`,
  );
  check('the pressed tab is the current one', after.marked.includes('filter=wrong'), after.marked);
  check('focus stayed on the tab that was pressed', after.focused.includes('filter=wrong'), after.focused);

  // Back is the same swap in reverse, and it must not load a document either.
  await page.evaluate(() => history.back());
  await page.waitForFunction(
    () => document.querySelectorAll('[data-review-item]').length === 110,
    undefined,
    { timeout: 15000 },
  );

  const back = await page.evaluate(() => ({
    marker: window.__reviewSmoke ?? null,
    url: window.location.search,
    marked: document.querySelector('[data-review-tab][aria-current="page"]')?.getAttribute('href') ?? '',
  }));

  check('Back restores the previous list without a load', back.marker === 'same document', `marker=${back.marker}`);
  check('Back moves the address bar back too', !back.url.includes('filter=wrong'), back.url || '(no query)');
  check('Back re-marks the tab it returned to', !back.marked.includes('filter=wrong'), back.marked);

  await settlePage(page);
  const phoneReport = await measureLayout(page);
  const phoneDefects = countDefects(phoneReport);
  printReport('result page @ 390px', phoneReport);
  check('the phone layout has no defects', phoneDefects === 0, `${phoneDefects} defects`);

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${resultUrl}?filter=wrong`, { waitUntil: 'domcontentloaded' });
  await settlePage(page);
  const desktopReport = await measureLayout(page);
  const desktopDefects = countDefects(desktopReport);
  printReport('result page @ 1440px', desktopReport);
  check('the desktop layout has no defects', desktopDefects === 0, `${desktopDefects} defects`);

  check('no console errors anywhere', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  // The tabs are ordinary links, so a browser with scripting off has to land on the
  // same list. Without this the "works without JavaScript" claim is only a comment.
  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });

  await noScript.addCookies([{
    name: cookieName,
    value: cookie,
    domain: new URL(baseUrl).hostname,
    path: '/',
    httpOnly: true,
    secure: baseUrl.startsWith('https'),
  }]);

  const plain = await noScript.newPage();

  await plain.goto(`${resultUrl}?filter=wrong`, { waitUntil: 'domcontentloaded' });

  const plainRows = await plain.locator('[data-review-item]').count();

  check(
    'a browser with scripting off still gets the filtered list',
    plainRows === wrong.rows,
    `${plainRows} rows, server said ${wrong.rows}`,
  );
  check(
    'and still gets the filter tabs as links',
    (await plain.locator('[data-review-tab]').count()) === 5,
    `${await plain.locator('[data-review-tab]').count()} tabs`,
  );

  await noScript.close();
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} ok, ${failed.length} failed`);
// A node script that falls off the end aborts with SIGABRT on this host.
process.exit(failed.length === 0 ? 0 : 1);
