// Asks the tutor about an item of a finished sitting, in a real browser, against
// a real gateway.
//
// This is the one check the PHP suite cannot make: whether the model, told what
// this page tells it, actually answers the question on the page. Everything else
// about the feature is asserted in tests/Feature/Exam/QuestionChatTest.php with
// the gateway faked; what is verified here is the part that only exists at
// runtime — a real round trip through LAM-Router, the answer stored, and the
// thread still there after a reload.
//
// It does not sit a paper. The sitting takes 110 answers and tools/exam-smoke.mjs
// already covers that; this script expects a finished sitting to exist and signs
// in to it, so its subject stays the conversation. Create one with:
//
//   php artisan tinker --execute='...'   (see docs/WORKING-NOTES.md)
//
// Usage:
//   CHAT_SMOKE_COOKIE="$(php artisan tinker --execute="require 'tools/smoke-session.php';" | tail -1)" \
//     node tools/question-chat-smoke.mjs https://cpns.buatjalan.xyz 12
//
// The session comes in as a cookie rather than an email and password: the page is
// behind the login gate, and a smoke test has no business being handed anybody's
// credentials. tools/smoke-session.php borrows a cookie from the application and
// puts the login in it. The account should still be a throwaway: this script
// writes real rows and spends real tokens.
import { chromium } from 'playwright-core';
import { countDefects, measureLayout, printReport, settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8000', attemptId = ''] = process.argv.slice(2);

const BROWSER = process.env.BROWSER_PATH
  ?? process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const cookie = process.env.CHAT_SMOKE_COOKIE ?? '';
const cookieName = process.env.CHAT_SMOKE_COOKIE_NAME ?? 'supa-cpns-session';

// A real generation has to come back, and a slow gateway is not a failure of this
// page — the panel tells the participant it can take a minute, so the script
// waits longer than that.
const ANSWER_TIMEOUT = 120_000;

const results = [];

const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};

if (cookie === '' || attemptId === '') {
  console.error('Set CHAT_SMOKE_COOKIE and pass an attempt id.');
  process.exit(2);
}

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
  // The session has to be the one the application would accept, or every check
  // below fails for the wrong reason and says nothing about the page.
  await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'domcontentloaded' });
  check('the injected session is accepted', !page.url().includes('/masuk'), page.url());

  if (page.url().includes('/masuk')) throw new Error('not signed in');

  // The offer on the result page.
  const resultUrl = `${baseUrl}/hasil/${attemptId}`;
  await page.goto(resultUrl, { waitUntil: 'domcontentloaded' });
  await settlePage(page);

  const links = page.locator('a[href*="/soal/"][href*="/tanya"]');
  const offers = await links.count();

  check('every item in the review offers the tutor', offers === 110, `${offers} links`);

  if (offers === 0) throw new Error('nothing to open');

  // Read the addresses while this page is still the one on screen: a locator is
  // resolved lazily, and the same call after the click below would search the
  // question page — which has no such links — and come back empty.
  const hrefs = await links.evaluateAll((nodes) => nodes.map((node) => node.href));

  // The offer lives inside the collapsed row, so it must not be reachable until
  // the row is opened: the list stays a list you scan, not one you scroll.
  check('the offer waits until the row is opened', !(await links.first().isVisible()));

  await page.locator('[data-review-item]').first().locator('summary').click();

  // Opening it: the link goes to the item's own page.
  await links.first().click();
  await page.waitForURL('**/tanya*', { timeout: 20000 });
  check('the link opens the item page', /\/hasil\/\d+\/soal\/\d+\/tanya/.test(page.url()), page.url());

  check('the page carries the item, not just a form', (await page.locator('ul li').count()) >= 5);
  check('the box is offered', (await page.locator('[data-chat-form] textarea[name="message"]').count()) === 1);

  // The script needs an item nobody has asked about yet. Every assertion below
  // counts turns, so on a second run against the same sitting — or on any item
  // that has been asked about before — they would be measuring what an earlier
  // visit left behind, and read as four failures while nothing was wrong with the
  // page. Walk the list until one page opens with an empty thread.
  let questionUrl = '';

  for (const href of hrefs) {
    await page.goto(href, { waitUntil: 'domcontentloaded' });

    if ((await page.locator('[data-chat-turn]').count()) === 0) {
      questionUrl = href;
      break;
    }
  }

  check('an item with no conversation yet is found', questionUrl !== '');

  if (questionUrl === '') {
    throw new Error('every item of this sitting already has a conversation');
  }

  check('the conversation starts empty', (await page.locator('[data-chat-turn]').count()) === 0);

  // Phone layout: this is the viewport participants actually use, and the page
  // that has to hold a long answer in a column.
  const phoneReport = await measureLayout(page);
  const phoneDefects = countDefects(phoneReport);
  printReport('question page @ 390px', phoneReport);
  check('the phone layout has no defects', phoneDefects === 0, `${phoneDefects} defects`);

  // Ask a real question, typed.
  await page.fill('[data-chat-form] textarea[name="message"]', 'Kenapa jawabanku belum tepat pada soal ini?');
  await page.click('[data-chat-submit]');
  await page.waitForSelector('[data-chat-turn="assistant"]', { timeout: ANSWER_TIMEOUT });

  const answer = (await page.locator('[data-chat-turn="assistant"]').first().innerText()).trim();

  check('the gateway answered through the page', answer.length > 40, `${answer.length} chars`);
  check('the answer is in Indonesian prose, not markup', !answer.includes('**') && !answer.includes('##'));
  check('the question is shown above the answer', (await page.locator('[data-chat-turn="user"]').count()) === 1);

  // The thread is stored, not just rendered once.
  await page.reload({ waitUntil: 'domcontentloaded' });
  check('the thread survives a reload', (await page.locator('[data-chat-turn]').count()) === 2);

  // A second turn: the thread grows and the item is still what is being discussed.
  await page.locator('button[name="saran"]').first().click();
  await page.waitForSelector('[data-chat-turn="assistant"] >> nth=1', { timeout: ANSWER_TIMEOUT });
  check('a suggested question also gets an answer', (await page.locator('[data-chat-turn]').count()) === 4);

  await settlePage(page);
  const phoneReport2 = await measureLayout(page);
  const phoneDefects2 = countDefects(phoneReport2);
  printReport('question page with a thread @ 390px', phoneReport2);
  check('the layout still holds with an answer on it', phoneDefects2 === 0, `${phoneDefects2} defects`);

  // The result page now knows a conversation exists.
  await page.goto(resultUrl, { waitUntil: 'domcontentloaded' });
  check('the review list marks the item as discussed', (await page.locator('a[href*="/tanya"] span').count()) >= 1);

  // Desktop, where the page is a wide column and the same content must not break.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(questionUrl, { waitUntil: 'domcontentloaded' });
  await settlePage(page);
  const desktopReport = await measureLayout(page);
  const desktopDefects = countDefects(desktopReport);
  printReport('question page @ 1440px', desktopReport);
  check('the desktop layout has no defects', desktopDefects === 0, `${desktopDefects} defects`);

  check('no console errors anywhere', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} ok, ${failed.length} failed`);
// A node script that falls off the end aborts with SIGABRT on this host.
process.exit(failed.length === 0 ? 0 : 1);
