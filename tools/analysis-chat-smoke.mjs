// Talks back to SUPA Bot about a finished sitting's analysis, in a real browser,
// against a real gateway.
//
// This is the one check the PHP suite cannot make. tests/Feature/Exam/
// AnalysisChatTest.php asserts what the conversation stores, prints and refuses,
// with the gateway faked; what is verified here only exists at runtime: that the
// button produces a real analysis, that the conversation reads as one — the
// participant's turns on the right, the assistant's on the left, which is
// geometry and cannot be asserted from markup — that Enter sends a message, and
// that a real answer comes back through LAM-Router and is still there after a
// reload.
//
// It expects a finished sitting with NO analysis yet, so the first thing it does
// is the real thing a participant does. Create one with tools/smoke-paper.php,
// which resets that state on every run.
//
// Usage:
//   CHAT_SMOKE_COOKIE="$(php artisan tinker --execute="require 'tools/smoke-session.php';" | tail -1)" \
//     node tools/analysis-chat-smoke.mjs https://cpns.buatjalan.xyz 12
import { chromium } from 'playwright-core';
import { countDefects, measureLayout, printReport, settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8000', attemptId = ''] = process.argv.slice(2);

const BROWSER = process.env.BROWSER_PATH
  ?? process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const cookie = process.env.CHAT_SMOKE_COOKIE ?? '';
const cookieName = process.env.CHAT_SMOKE_COOKIE_NAME ?? 'supa-cpns-session';

// An analysis is the gateway writing five short sections, and a follow-up answer
// is a few paragraphs. The panel tells the participant it can take a minute, so
// the script waits longer than that rather than calling a slow gateway a defect.
const ANSWER_TIMEOUT = 150_000;

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

/** Where every turn of the conversation sits, in the page's own coordinates. */
const turnGeometry = () => page.evaluate(() => {
  const box = (node) => {
    const rect = node.getBoundingClientRect();

    return { left: Math.round(rect.left), right: Math.round(rect.right) };
  };

  const thread = document.querySelector('[data-chat-thread]');
  const turns = [...document.querySelectorAll('[data-chat-turn]')];

  return {
    thread: box(thread),
    assistant: turns.filter((node) => node.dataset.chatTurn === 'assistant').map(box),
    user: turns.filter((node) => node.dataset.chatTurn === 'user').map(box),
  };
});

try {
  await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'domcontentloaded' });
  check('the injected session is accepted', !page.url().includes('/masuk'), page.url());

  if (page.url().includes('/masuk')) throw new Error('not signed in');

  const resultUrl = `${baseUrl}/hasil/${attemptId}`;

  await page.goto(resultUrl, { waitUntil: 'domcontentloaded' });
  await settlePage(page);

  check('the result page offers the analysis', (await page.locator('[data-analysis]').count()) === 1);

  // The real generation, through the button a participant would press. On a
  // sitting that already has one the panel opens as a conversation instead, which
  // is asserted further down — but this run starts from nothing.
  const generate = page.locator('[data-analysis-form] button[data-analysis-submit]').first();

  if (await generate.count() > 0) {
    check('the conversation is not there before an analysis exists', (await page.locator('[data-chat-turn]').count()) === 0);

    await generate.click();
    await page.waitForSelector('[data-chat-turn="assistant"]', { timeout: ANSWER_TIMEOUT });
    check('the button produces an analysis', true);
  } else {
    check('a sitting that already has an analysis opens as a conversation', true);
  }

  const analysis = (await page.locator('[data-chat-turn="assistant"]').first().innerText()).trim();

  // Read the label from the DOM rather than from what is painted: the caption is
  // rendered in capitals by CSS, and the name the participant is given is "SUPA
  // Bot", which is the string that must be there.
  const label = (await page.locator('[data-chat-turn="assistant"]').first().locator('p').first().textContent()).trim();

  check('the bot is named SUPA Bot', label === 'SUPA Bot', label);
  check('the analysis is real prose, not markup', analysis.length > 200 && !analysis.includes('**') && !analysis.includes('##'), `${analysis.length} chars`);
  check('the request that produced it is the participant\'s own turn', (await page.locator('[data-chat-turn="user"]').count()) === 1);

  // Which side each turn sits on. This is the requirement that markup cannot
  // express: both sides are the same component, and only the measured position
  // says whether the page reads as a conversation.
  const geometry = await turnGeometry();
  const ask = geometry.user.at(-1);
  const reply = geometry.assistant.at(-1);

  check('the assistant answers from the left', reply.left - geometry.thread.left <= 12, `${reply.left - geometry.thread.left}px from the left edge`);
  check('the participant asks from the right', geometry.thread.right - ask.right <= 12, `${geometry.thread.right - ask.right}px from the right edge`);
  check('the two sides do not overlap', ask.left > reply.left, `ask ${ask.left}, reply ${reply.left}`);

  // Enter sends, and it is the only thing that does: this is typed into the box
  // and sent without the button ever being pressed.
  const composer = page.locator('[data-chat-form] textarea[name="message"]');

  // Shift+Enter is the escape hatch, and it has to be, or a participant cannot
  // write a second line.
  await composer.click();
  await page.keyboard.type('baris satu');
  await page.keyboard.down('Shift');
  await page.keyboard.press('Enter');
  await page.keyboard.up('Shift');
  await page.keyboard.type('baris dua');

  check('Shift+Enter makes a line instead of sending', (await composer.inputValue()).includes('\n')
    && (await page.locator('[data-chat-turn]').count()) === 2);

  await composer.fill('Kenapa rencana belajarnya harus mulai dari situ?');
  await page.keyboard.press('Enter');

  await page.waitForSelector('[data-chat-turn="assistant"] >> nth=1', { timeout: ANSWER_TIMEOUT });

  const answer = (await page.locator('[data-chat-turn="assistant"]').nth(1).innerText()).trim();

  check('Enter sends the message', (await page.locator('[data-chat-turn]').count()) === 4);
  check('the answer is real, in Indonesian prose, not markup', answer.length > 40 && !answer.includes('**') && !answer.includes('##'), `${answer.length} chars`);

  // The new question is the participant's, and it is on their side.
  const afterGeometry = await turnGeometry();
  const question = afterGeometry.user.at(-1);

  check('the new question sits on the participant\'s side', question.left > afterGeometry.assistant.at(-1).left, `ask ${question.left}, answer ${afterGeometry.assistant.at(-1).left}`);

  // The conversation is stored, not just rendered once.
  await page.reload({ waitUntil: 'domcontentloaded' });
  check('the conversation survives a reload', (await page.locator('[data-chat-turn]').count()) === 4);

  await settlePage(page);
  const phoneReport = await measureLayout(page);
  const phoneDefects = countDefects(phoneReport);
  printReport('result page with a conversation @ 390px', phoneReport);
  check('the phone layout has no defects', phoneDefects === 0, `${phoneDefects} defects`);

  // Desktop, where the section is a wide column and the same conversation has to
  // hold together — bubbles that fill the width there would stop reading as a
  // conversation at all.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(resultUrl, { waitUntil: 'domcontentloaded' });
  await settlePage(page);

  const desktopReport = await measureLayout(page);
  const desktopDefects = countDefects(desktopReport);
  printReport('result page with a conversation @ 1440px', desktopReport);
  check('the desktop layout has no defects', desktopDefects === 0, `${desktopDefects} defects`);

  const wide = await turnGeometry();
  const widest = Math.max(...[...wide.user, ...wide.assistant].map((turn) => turn.right - turn.left));

  check('the turns stay a readable width on a wide screen', widest < wide.thread.right - wide.thread.left, `widest turn ${widest}px of ${wide.thread.right - wide.thread.left}px`);

  check('no console errors anywhere', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} ok, ${failed.length} failed`);
// A node script that falls off the end aborts with SIGABRT on this host.
process.exit(failed.length === 0 ? 0 : 1);
