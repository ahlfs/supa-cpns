// Audit and screenshot the pages a signed-in participant sees: the dashboard,
// the package list, the exam room, and the score analysis.
//
// The marketing pages are covered by tools/page-shots.mjs and
// tools/layout-audit.mjs, which need no account. These pages cannot be reached
// without one, so they were the ones nobody had ever looked at — which is
// exactly where stacked and misaligned layout survives a green PHP suite.
//
// Usage: node tools/authed-audit.mjs <base-url> <output-dir>
//
// It registers a throwaway account, measures every page at both breakpoints,
// then answers a whole sitting through the same endpoint the buttons call and
// measures the analysis page it unlocks. Pass --keep to leave the account behind.
//
// The host quirk that matters: a node script that falls off the end aborts with
// SIGABRT here, so the run always exits through process.exit().

import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright-core';
import { countDefects, measureLayout, printReport, settlePage } from './lib/layout-measure.mjs';

const [baseUrl = 'http://127.0.0.1:8123', outDir = '/tmp/authed-audit'] = process.argv.slice(2);
const keep = process.argv.includes('--keep');
// Set AUDIT_EMAIL / AUDIT_PASSWORD to audit an existing account (an admin, say)
// instead of registering a throwaway participant. The values come from the
// environment, so a credential never passes through this file or the transcript.
const loginEmail = process.env.AUDIT_EMAIL ?? '';
const loginPassword = process.env.AUDIT_PASSWORD ?? '';
const registeredInstead = loginEmail === '';

const BROWSER = process.env.BROWSER_PATH
  ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome';

const viewports = [
  ['desktop', 1440, 900],
  ['phone', 390, 844],
];

mkdirSync(outDir, { recursive: true });

const email = `audit-${Date.now()}@example.test`;
const password = 'rahasia123';

/** Answer every item through the endpoint the option click uses.
 *
 * Clicking 110 options would take minutes and measure the network rather than
 * the layout. The payload field is `selected_option_id`, and the option id lives
 * on `data-option-id` — guessing either one silently stored nothing on the first
 * attempt, and the run then measured the exam room as if it were the result page.
 */
async function answerEverything(page) {
  const result = await page.evaluate(async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const attempt = location.pathname.split('/').pop();
    const ids = [...new Set([...document.querySelectorAll('[data-exam-cell]')]
      .map((cell) => cell.dataset.questionId))];

    let stored = 0;
    let firstFailure = '';

    for (const questionId of ids) {
      const item = document.querySelector(`[data-exam-item][data-exam-item="${questionId}"]`)
        ?? document.querySelector('[data-exam-item]:not([hidden])');
      const optionId = item?.querySelector('[data-exam-option]')?.dataset.optionId;

      const response = await fetch(`/tryout/attempt/${attempt}/jawaban`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ question_id: Number(questionId), selected_option_id: Number(optionId) }),
      });

      if (response.ok) stored += 1;
      else if (!firstFailure) firstFailure = `${response.status} ${await response.text()}`.slice(0, 300);
    }

    return { total: ids.length, stored, firstFailure };
  });

  console.log(`answered ${result.stored}/${result.total} items`);
  if (result.firstFailure) throw new Error(`the answer endpoint refused a write: ${result.firstFailure}`);

  return result;
}

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
// One context so the session cookie carries across every page and viewport.
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

const consoleErrors = [];
page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push(String(error)));

const problems = [];
const summary = [];

/** Load, settle, measure, screenshot, and report one page at one width. */
async function audit(path, slug, [name, width, height]) {
  await page.setViewportSize({ width, height: height });

  const response = await page.goto(new URL(path, baseUrl).toString(), { waitUntil: 'networkidle' });
  await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});
  await settlePage(page);

  const report = await measureLayout(page);
  const file = `${outDir}/${slug}-${name}.png`;
  // The exam room is pinned to the viewport, so a full-page capture of it is one
  // screen tall and says nothing; every other page is a document worth seeing whole.
  await page.screenshot({ path: file, fullPage: slug !== 'room' });

  const defects = countDefects(report);
  const label = `${name} ${path}`;
  summary.push(`${label} -> ${response?.status()} ${report.docHeight}px defects=${defects} -> ${file}`);
  console.log(`\n### ${label} -> ${response?.status()} ${report.docWidth}x${report.docHeight} defects=${defects} -> ${file}`);
  printReport(`${path} @ ${width}px`, report);

  if (response?.status() !== 200 || report.scrollWidth > report.docWidth) problems.push(label);

  return report;
}

/** The participant route: dashboard, package list, exam room, analysis page. */
async function auditTheParticipantFlow() {
  await page.goto(`${baseUrl}/tryout`, { waitUntil: 'domcontentloaded' });
  const starter = page.locator('form[action$="/mulai"] button[type="submit"]').first();

  if ((await starter.count()) === 0) {
    console.log('no published package — auditing the dashboard alone');
    for (const viewport of viewports) await audit('/dashboard', 'dashboard', viewport);

    return;
  }

  await starter.click();
  await page.waitForURL('**/tryout/attempt/**');

  const roomPath = new URL(page.url()).pathname;
  const attemptId = roomPath.split('/').pop();
  console.log(`sitting ${roomPath}`);

  for (const viewport of viewports) await audit('/dashboard', 'dashboard', viewport);
  for (const viewport of viewports) await audit('/tryout', 'tryout', viewport);
  for (const viewport of viewports) await audit(roomPath, 'room', viewport);

  // The walk above left the viewport at phone width; answer at desktop size.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(new URL(roomPath, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await answerEverything(page);

  await page.evaluate(() => document.querySelector('form[data-exam-finish]').submit());
  await page.waitForURL('**/hasil/**', { timeout: 30000 });

  for (const viewport of viewports) await audit(`/hasil/${attemptId}`, 'hasil', viewport);
}

try {
  // --- Sign in, or register a throwaway participant when no account was given.
  if (registeredInstead) {
    await page.goto(`${baseUrl}/daftar`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="name"]', 'Peserta Audit');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.fill('input[name="password_confirmation"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard').catch(() => {});
  } else {
    await page.goto(`${baseUrl}/masuk`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', loginEmail);
    await page.fill('input[name="password"]', loginPassword);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/masuk')).catch(() => {});
  }

  if (/\/masuk/.test(new URL(page.url()).pathname)) {
    throw new Error(`sign-in did not leave the login page (landed on ${page.url()})`);
  }

  console.log(`signed in as ${registeredInstead ? email : loginEmail} -> ${page.url()}`);

  // An administrator sees the panel instead of the participant flow.
  if (!registeredInstead) {
    for (const viewport of viewports) {
      for (const [path, slug] of [
        ['/admin', 'admin-dashboard'],
        ['/admin/soal', 'admin-questions'],
        ['/admin/paket', 'admin-packages'],
        ['/admin/impor', 'admin-import'],
        ['/admin/pengguna', 'admin-users'],
      ]) {
        await audit(path, slug, viewport);
      }
    }
  } else {
    await auditTheParticipantFlow();
  }
} finally {
  await browser.close();
}

console.log('\n===== SUMMARY =====');
for (const line of summary) console.log(line);
if (consoleErrors.length > 0) console.log('\nconsole errors:', [...new Set(consoleErrors)]);
console.log(problems.length === 0 ? '\nPAGES CLEAN' : `\nPROBLEMS: ${problems.join(', ')}`);
if (registeredInstead && !keep) console.log(`(delete the throwaway account: ${email})`);

process.exit(0);
