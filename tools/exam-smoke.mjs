// Drives a whole CPNS sitting in a real browser: register, start, answer, flag,
// move on with the navigator, reload and resume, finish, and read the analysis
// page.
//
// It navigates with the NEXT control rather than the skip control on purpose:
// skip clears the item it is invoked on (the server stores a null-option row), so
// a script that skipped and then claimed the answer had survived the reload was
// reading a cell it had emptied itself. Skip semantics are covered by the PHP
// suite (AnswerEndpointTest), where a null option is asserted against the store.
//
// Separate from tools/live-smoke.mjs on purpose: that one checks the marketing page
// against the deployed site without touching the database, while this one needs an
// account and writes a full attempt. Run it against the loopback port first, then
// against the public URL.
//
// Usage: node tools/exam-smoke.mjs http://127.0.0.1:8000
//
// The browser binary comes from BROWSER_PATH, the same variable every other
// script in tools/ reads, so an operator who sets one variable gets the same
// chrome in all of them; CHROME_PATH is still honoured as a fallback.
import { chromium } from 'playwright-core';

const BASE = process.argv[2] ?? 'http://127.0.0.1:8000';
const BROWSER = process.env.BROWSER_PATH
  ?? process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const results = [];

const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};

const email = `smoke-${Date.now()}@example.test`;

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
const consoleErrors = [];

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push(String(error)));

try {
  // Register.
  await page.goto(`${BASE}/daftar`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', 'Peserta Smoke');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'rahasia123');
  await page.fill('input[name="password_confirmation"]', 'rahasia123');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  check('register lands on the dashboard', page.url().includes('/dashboard'));

  await page.goto(`${BASE}/tryout`, { waitUntil: 'domcontentloaded' });

  const starters = page.locator('form[action$="/mulai"] button[type="submit"]');

  if ((await starters.count()) === 0) {
    check('a published package exists to sit', false, 'create and publish a package first');
  } else {
    await starters.first().click();
    await page.waitForURL('**/tryout/attempt/**');

    const attemptUrl = page.url();
    check('starting opens the exam room', /\/tryout\/attempt\/\d+$/.test(attemptUrl));

    const total = Number(await page.getAttribute('[data-exam]', 'data-total'));
    check('the room states the item count from config', total === 110, `total=${total}`);

    const clockBefore = await page.textContent('[data-exam-clock]');
    await page.waitForTimeout(2200);
    check('the clock counts down', clockBefore !== (await page.textContent('[data-exam-clock]')));

    await page.locator('[data-exam-item]:not([hidden]) [data-exam-option]').first().click();
    await page.waitForFunction(() => document.querySelector('[data-exam-cell]')?.dataset.status === 'answered');

    const firstCell = page.locator('[data-exam-cell]').first();

    check('answering turns the first navigator cell green', (await firstCell.getAttribute('data-status')) === 'answered');
    check('the answered counter follows', (await page.textContent('[data-exam-answered]')).trim() === '1');

    await page.locator('[data-exam-item]:not([hidden]) [data-exam-flag]').click();
    await page.waitForFunction(() => document.querySelector('[data-exam-cell]')?.dataset.flag === 'true');
    check('flagging marks the cell', (await firstCell.getAttribute('data-flag')) === 'true');

    // Move on with NEXT, not with the skip control. Skip CLEARS the item it is
    // invoked on and advances — the server stores a null-option row — so the
    // reload assertion below would be reading the very cell the script had just
    // emptied: the old order here (answer, flag, SKIP, reload, "the answer
    // survives") failed on every run, while the check beside it read a cell the
    // script never touched and passed for that reason alone.
    await page.click('[data-exam-next]');

    const secondCell = page.locator('[data-exam-cell]').nth(1);

    check(
      'next moves to the second item',
      (await page.locator('[data-exam-item]:not([hidden])').getAttribute('data-exam-item'))
        === (await secondCell.getAttribute('data-question-id')),
    );
    check('the second item is genuinely untouched and blank', (await secondCell.getAttribute('data-status')) === 'blank',
      `status=${await secondCell.getAttribute('data-status')}`);
    check('the first item stays answered after moving away', (await firstCell.getAttribute('data-status')) === 'answered');

    // Two writes (the answer and the flag) are still in flight when the moving
    // click returns, and this script then reloads: let them land, or the reload
    // assertion measures the network rather than the server.
    await page.waitForTimeout(600);

    await page.reload({ waitUntil: 'domcontentloaded' });
    check('the answer survives a reload', (await firstCell.getAttribute('data-status')) === 'answered');
    check('the review mark survives a reload', (await firstCell.getAttribute('data-flag')) === 'true');
    check('the untouched item is still blank after a reload',
      (await page.locator('[data-exam-cell]').nth(1).getAttribute('data-status')) === 'blank');

    // Answer every remaining item by walking the sitting with NEXT. Two things this
    // loop must not do, both learned the hard way: it must not click navigator cells
    // (on the phone viewport this script runs at, the cells live in the bottom sheet
    // and are off-screen while it is closed, so Playwright waits forever for a click
    // that cannot land), and it must not walk on before the answer has actually
    // landed — the option write is a request, and racing it leaves items blank, which
    // then leaves the finish button disabled and looks like a server bug.
    const ids = [...new Set(await page.locator('[data-exam-cell]').evaluateAll(
      (cells) => cells.map((cell) => cell.dataset.questionId),
    ))];

    // Walk every item by addressing it in the navigator rather than by pressing NEXT.
    // Two reasons, both paid for: on the phone viewport this script runs at, the
    // navigator's cells live in the bottom sheet and are off-screen while it is
    // closed (Playwright then waits forever for a click that cannot land), and NEXT
    // moves relative to wherever the page thinks it is, so a slow position save can
    // turn two quick presses into one skipped item. An address cannot skip. Choosing
    // an item closes the sheet again by itself.
    for (const questionId of ids) {
      const sheetOpen = await page.locator('[data-exam-sheet]').evaluate((node) => node.dataset.open === 'true');

      if (!sheetOpen) await page.click('[data-exam-sheet-toggle]');

      await page.locator(`[data-exam-cell][data-question-id="${questionId}"]`).first().click();
      await page.waitForFunction(
        (id) => document.querySelector('[data-exam-item]:not([hidden])')?.dataset.examItem === id,
        questionId,
      );

      const current = page.locator('[data-exam-item]:not([hidden])');
      const answeredBefore = Number((await page.textContent('[data-exam-answered]')).trim());

      if ((await current.locator('[data-exam-option][data-selected="true"]').count()) === 0) {
        await current.locator('[data-exam-option]').first().click();

        // The cell turns green the moment the option is clicked, before the write has
        // landed, so the colour cannot be the signal to walk on: a request that fails
        // reverts the cell, and a walk that trusted the colour would move on and leave
        // the item blank — which is how the finish button once ended up disabled and
        // looked like a server fault. The answered counter only moves when the server
        // has the answer.
        await page.waitForFunction(
          (previous) => Number(document.querySelector('[data-exam-answered]')?.textContent.trim()) > previous,
          answeredBefore,
          { timeout: 15000 },
        );
      }
    }

    const blank = [...new Set(await page.locator('[data-exam-cell][data-status="blank"]').evaluateAll(
      (cells) => cells.map((cell) => cell.dataset.questionId),
    ))];

    check(
      'all items are answered',
      (await page.textContent('[data-exam-answered]')).trim() === String(total),
      blank.length > 0 ? `masih kosong: ${blank.join(', ')}` : '',
    );

    // The navigator is exercised after the walk, with the sheet open — choosing an
    // item closes the sheet again by itself.
    await page.click('[data-exam-sheet-toggle]');
    await page.locator('[data-exam-cell]').nth(4).click();
    await page.waitForTimeout(200);

    check(
      'the navigator jumps to the chosen item',
      (await page.locator('[data-exam-item]:not([hidden])').getAttribute('data-exam-item'))
        === (await page.locator('[data-exam-cell]').nth(4).getAttribute('data-question-id')),
    );

    // On a phone the finish button lives inside the bottom sheet, on purpose.
    await page.click('[data-exam-sheet-toggle]');
    await page.locator('[data-exam-sheet] [data-exam-finish-button]').click();
    await page.waitForURL('**/hasil/**', { timeout: 30000 });
    check('finishing lands on the analysis page', page.url().includes('/hasil/'));

    await page.waitForSelector('text=Pembahasan');
    check('the analysis page renders its review', true);

    await page.goto(`${attemptUrl.replace('/tryout/attempt/', '/hasil/')}?filter=wrong`, { waitUntil: 'domcontentloaded' });
    check('the review filter is a real URL', page.url().includes('filter=wrong'));

    check('no console errors during the whole sitting', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));
  }
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} ok, ${failed.length} failed`);
process.exit(failed.length === 0 ? 0 : 1);
