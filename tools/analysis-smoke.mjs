// Drive the AI analysis on a real deployment: register, sit a whole paper, ask
// for the analysis, and read what comes back.
//
// The PHP suite proves the wiring against a faked gateway. This proves the other
// half — that the configured gateway actually answers, that the panel renders
// the text, and that exactly one analysis row is written. It needs the network,
// an account and a published package, so it is not part of `php artisan test`.
//
// Usage: node tools/analysis-smoke.mjs <base-url>
//
// The host quirk that matters: a node script that falls off the end aborts with
// SIGABRT here, so the run always exits through process.exit().

import { chromium } from 'playwright-core';

const baseUrl = process.argv[2] ?? 'http://127.0.0.1:8123';

const BROWSER = process.env.BROWSER_PATH
  ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome';

const email = `analysis-${Date.now()}@example.test`;
const results = [];

const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

try {
  // --- An account with a finished sitting on it.
  await page.goto(`${baseUrl}/daftar`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', 'Peserta Analisa');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'rahasia123');
  await page.fill('input[name="password_confirmation"]', 'rahasia123');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');

  await page.goto(`${baseUrl}/tryout`, { waitUntil: 'domcontentloaded' });
  const starter = page.locator('form[action$="/mulai"] button[type="submit"]').first();

  if ((await starter.count()) === 0) {
    console.log('no published package — nothing to analyse');
    await browser.close();
    process.exit(1);
  }

  await starter.click();
  await page.waitForURL('**/tryout/attempt/**');

  // Every fourth option, so the paper is neither perfect nor blank and the
  // numbers the model is given have something to talk about.
  const answered = await page.evaluate(async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const attempt = location.pathname.split('/').pop();
    const ids = [...new Set([...document.querySelectorAll('[data-exam-cell]')]
      .map((cell) => cell.dataset.questionId))];

    let stored = 0;
    for (const questionId of ids) {
      const item = document.querySelector(`[data-exam-item][data-exam-item="${questionId}"]`)
        ?? document.querySelector('[data-exam-item]:not([hidden])');
      const options = [...(item?.querySelectorAll('[data-exam-option]') ?? [])];
      const pick = options[Math.min(2, options.length - 1)];

      const response = await fetch(`/tryout/attempt/${attempt}/jawaban`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
          question_id: Number(questionId),
          selected_option_id: Number(pick?.dataset.optionId),
        }),
      });

      if (response.ok) stored += 1;
    }

    return { total: ids.length, stored };
  });

  check('every item could be answered', answered.stored === answered.total, `${answered.stored}/${answered.total}`);

  await page.evaluate(() => document.querySelector('form[data-exam-finish]').submit());
  await page.waitForURL('**/hasil/**', { timeout: 30000 });

  check('the sitting is submitted', /\/hasil\//.test(page.url()), page.url());

  // --- The panel exists, and asking for the analysis produces text.
  const button = page.locator('form[action$="/analisa-ai"] button[type="submit"]');

  check('the analysis panel is offered on the result page', (await button.count()) > 0);

  if ((await button.count()) === 0) {
    await browser.close();
    process.exit(1);
  }

  await button.first().click();

  // A real generation takes seconds; the ceiling here is the gateway's own
  // timeout plus room for the redirect and render.
  await page.waitForSelector('[data-analysis-text]', { timeout: 150000 });

  const text = (await page.textContent('[data-analysis-text]')).trim();

  check('the analysis came back with a real body', text.length > 200, `${text.length} chars`);
  check('it names the participant informally, as instructed', /kamu/i.test(text));
  check('it talks about the subtests the paper actually has', /TWK|TIU|TKP/.test(text));
  check('it is labelled as machine-written advice', /bukan penilaian resmi/i.test(await page.textContent('[data-analysis]')));

  console.log(`\n--- analysis (${text.length} chars) ---\n${text.slice(0, 1200)}\n--- end ---\n`);

  const file = '/tmp/analysis-result.png';
  await page.screenshot({ path: file, fullPage: false });
  console.log(`screenshot: ${file}`);

  // Regenerating must replace the row rather than add one, and reloading must
  // not spend a second call.
  const before = Date.now();
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('[data-ai-analysis-text]', { timeout: 20000 });
  check('a reload shows the stored analysis without asking the gateway again', Date.now() - before < 15000);
} catch (error) {
  check('the run completed', false, error.message);
} finally {
  await browser.close();
}

const failed = results.filter((row) => !row.ok);
console.log(`\n${results.length - failed.length}/${results.length} ok`);
console.log(`(delete the throwaway account: ${email})`);

process.exit(failed.length === 0 ? 0 : 1);
