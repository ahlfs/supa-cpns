// Exercise the deployed page in a real browser: the interactions the reference
// page has, against whatever URL is live, not against a local dev server.
//
//   node tools/live-smoke.mjs --url=https://cpns.buatjalan.xyz/
//   node tools/live-smoke.mjs --url=http://127.0.0.1:8080/     # same checks, local
//
// A pixel diff cannot see any of this: a section can match the reference to
// within a pixel while its tab set, its menu, its program panels or its drawer do
// nothing because the bundle did not load. Each check drives the page the way a
// visitor would and reads the state the module is supposed to publish.
import { chromium } from 'playwright-core';

const args = Object.fromEntries(
  process.argv.slice(2).map((arg) => {
    const [key, value = 'true'] = arg.replace(/^--/, '').split('=');
    return [key, value];
  }),
);

const URL = args.url ?? 'http://127.0.0.1:8080/';
const BROWSER = process.env.BROWSER_PATH ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome';

// Any console error is a regression. Nothing is tolerated here: the icon set is
// ours now and every glyph in it is well-formed, so a browser complaint about a
// path means the page asked for an icon it should not have.
let pass = 0;
let fail = 0;
const ok = (message) => { console.log(`ok   ${message}`); pass += 1; };
const bad = (message) => { console.log(`FAIL ${message}`); fail += 1; };
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const errors = [];

async function open(viewport) {
  const page = await browser.newPage({ viewport });
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text().slice(0, 200));
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${String(error).slice(0, 200)}`));

  const response = await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForLoadState('networkidle', { timeout: 25000 }).catch(() => {});
  await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});
  return { page, status: response?.status() ?? 0 };
}

console.log(`\n== ${URL}`);

const { page: desktop, status } = await open({ width: 1440, height: 900 });
status === 200 ? ok(`GET / answers 200`) : bad(`GET / answers ${status}`);

const title = await desktop.title();
title.includes('SUPA CPNS') ? ok(`title: ${title}`) : bad(`title does not name the site: ${title}`);

const broken = await desktop.evaluate(() =>
  [...document.images].filter((image) => image.complete && image.naturalWidth === 0).map((image) => image.currentSrc || image.src),
);
broken.length === 0 ? ok('every image decoded') : bad(`${broken.length} broken image(s): ${broken.slice(0, 3).join(', ')}`);

// The bundle has to have run for anything below to work.
const stylesApplied = await desktop.evaluate(() => {
  const header = document.querySelector('[data-site-header]');
  return header ? getComputedStyle(header).position !== 'static' : false;
});
stylesApplied ? ok('the built stylesheet is applied') : bad('the built stylesheet is not applied');

// --- header condenses on scroll -------------------------------------------
await desktop.evaluate(() => window.scrollTo({ top: 400, behavior: 'instant' }));
await sleep(350);
const scrolled = await desktop.getAttribute('[data-site-header]', 'data-scrolled');
scrolled === 'true' ? ok('header condenses past 20px of scroll') : bad(`header data-scrolled is '${scrolled}' at y=400`);
await desktop.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
await sleep(300);
const unscrolled = await desktop.getAttribute('[data-site-header]', 'data-scrolled');
unscrolled === 'false' ? ok('header expands again at the top') : bad(`header data-scrolled is '${unscrolled}' at y=0`);

// --- desktop mega menu -----------------------------------------------------
// The bar's items come from resources/content/site.php, and that list is empty by
// design: the two dropdowns duplicated destinations the page already offers. So a
// missing trigger is not a failure here — an orphaned panel is, and so is a
// dropdown that does not work when one is configured. Both directions are checked.
const trigger = desktop.locator('[data-menu-trigger]').first();
const menuCount = await desktop.locator('[data-menu-trigger]').count();
if (menuCount === 0) {
  const orphaned = await desktop.locator('[data-menu-panel]').count();
  orphaned === 0
    ? ok('the bar carries no mega menu (navigation.items is empty)')
    : bad(`${orphaned} [data-menu-panel] rendered without a trigger`);
} else {
  await trigger.click();
  await sleep(200);
  const open = await desktop.evaluate(() => {
    const item = document.querySelector('[data-nav-item]');
    const panel = item.querySelector('[data-menu-panel]');
    const box = panel.getBoundingClientRect();
    return {
      hidden: panel.hidden,
      state: item.dataset.open,
      aria: item.querySelector('[data-menu-trigger]').getAttribute('aria-expanded'),
      height: Math.round(box.height),
      links: panel.querySelectorAll('a').length,
    };
  });
  open.hidden === false && open.state === 'true' && open.aria === 'true' && open.height > 50 && open.links > 0
    ? ok(`mega menu opens (${open.links} links, ${open.height}px tall)`)
    : bad(`mega menu did not open as expected: ${JSON.stringify(open)}`);

  await desktop.mouse.click(700, 500);
  await sleep(250);
  const closed = await desktop.evaluate(() => document.querySelector('[data-menu-panel]').hidden);
  closed === true ? ok('an outside click closes the menu') : bad('the menu stayed open after an outside click');
}

// --- program panels (and their tab set, when there is more than one) --------
const tabs = await desktop.evaluate(() => [...document.querySelectorAll('[data-tab]')].map((tab) => tab.dataset.tab));
const panels = await desktop.evaluate(() => [...document.querySelectorAll('[data-program-panel]')].map((panel) => ({
  slug: panel.dataset.programPanel,
  hidden: panel.hidden,
  cards: panel.querySelectorAll('a').length,
})));

if (panels.length === 0) {
  bad('the program section rendered no panel at all');
} else if (panels.some((panel) => panel.cards === 0)) {
  bad(`a program panel has no cards: ${JSON.stringify(panels)}`);
} else if (tabs.length > 1) {
  // The tablist only renders for a multi-category program list; when it is there,
  // switching has to actually switch.
  await desktop.locator(`[data-tab="${tabs[1]}"]`).click();
  await sleep(250);
  const state = await desktop.evaluate((slug) => {
    const all = [...document.querySelectorAll('[data-program-panel]')];
    const active = all.find((panel) => panel.dataset.programPanel === slug);
    const others = all.filter((panel) => panel !== active);
    const tab = [...document.querySelectorAll('[data-tab]')].find((node) => node.dataset.tab === slug);
    return {
      activeHidden: active?.hidden,
      othersHidden: others.map((panel) => panel.hidden),
      aria: tab?.getAttribute('aria-selected'),
      cards: active ? active.querySelectorAll('a').length : 0,
      height: active ? Math.round(active.getBoundingClientRect().height) : 0,
    };
  }, tabs[1]);

  state.activeHidden === false && state.othersHidden.every(Boolean) && state.aria === 'true' && state.cards > 0 && state.height > 200
    ? ok(`switching to the "${tabs[1]}" tab shows its panel (${state.cards} cards, ${state.height}px tall) and hides the rest`)
    : bad(`tab switching is wrong: ${JSON.stringify(state)}`);
} else {
  const visible = panels.filter((panel) => !panel.hidden);
  visible.length === 1
    ? ok(`a single program category renders one visible panel (${visible[0].cards} cards); no tab row is offered`)
    : bad(`expected exactly one visible panel, got ${visible.length}: ${JSON.stringify(panels)}`);
}

await desktop.close();

// --- mobile: drawer, accordion, back to top --------------------------------
const { page: mobile, status: mobileStatus } = await open({ width: 390, height: 844 });
mobileStatus === 200 ? ok('GET / answers 200 at 390px') : bad(`GET / answers ${mobileStatus} at 390px`);

const toggle = mobile.locator('[data-drawer-toggle]');
if ((await toggle.count()) === 0) {
  bad('no [data-drawer-toggle] at 390px');
} else {
  await toggle.click();
  await sleep(250);
  const drawer = await mobile.evaluate(() => {
    const node = document.querySelector('[data-mobile-drawer]');
    const button = document.querySelector('[data-drawer-toggle]');
    return {
      hidden: node.hidden,
      open: button.dataset.open,
      aria: button.getAttribute('aria-expanded'),
      height: Math.round(node.getBoundingClientRect().height),
      links: node.querySelectorAll('a').length,
    };
  });
  /*
   * The drawer is the account block now: the bar carries no Beranda item, so its
   * link list renders only when `navigation.items` has something in it. A guest
   * gets the login and register pills, a signed-in visitor the dashboard and the
   * panel — two either way, which is the floor here. A populated list raises the
   * count rather than the floor.
   */
  drawer.hidden === false && drawer.open === 'true' && drawer.aria === 'true' && drawer.height > 60 && drawer.links >= 2
    ? ok(`mobile drawer opens (${drawer.links} links, ${drawer.height}px tall)`)
    : bad(`mobile drawer did not open as expected: ${JSON.stringify(drawer)}`);

  const accordion = mobile.locator('[data-drawer-accordion]').first();
  if ((await accordion.count()) === 0) {
    ok('the drawer carries no sub-lists (navigation.items is empty)');
  } else {
    const sublistBefore = await mobile.evaluate(() => document.querySelector('[data-drawer-accordion]').nextElementSibling.hidden);
    await accordion.click();
    await sleep(250);
    const sublistAfter = await mobile.evaluate(() => {
      const node = document.querySelector('[data-drawer-accordion]');
      return { hidden: node.nextElementSibling.hidden, open: node.dataset.open, items: node.nextElementSibling.querySelectorAll('a').length };
    });
    sublistAfter.hidden === !sublistBefore && sublistAfter.open === 'true' && sublistAfter.items > 0
      ? ok(`drawer accordion expands (${sublistAfter.items} sub-links)`)
      : bad(`drawer accordion did not expand: ${JSON.stringify({ sublistBefore, sublistAfter })}`);
  }

  await toggle.click();
  await sleep(200);
}

await mobile.evaluate(() => window.scrollTo({ top: 1200, behavior: 'instant' }));
await sleep(400);
const topButton = mobile.locator('[data-back-to-top]');
const visible = await topButton.getAttribute('data-visible');
const shown = await topButton.evaluate((node) => getComputedStyle(node).visibility === 'visible' && Number(getComputedStyle(node).opacity) > 0.9);
visible === 'true' && shown ? ok('back-to-top appears past 600px of scroll') : bad(`back-to-top state: data-visible='${visible}', rendered=${shown}`);

await topButton.click();
await sleep(1200);
const atTop = await mobile.evaluate(() => window.scrollY);
atTop < 5 ? ok(`back-to-top returns to the top (y=${atTop})`) : bad(`back-to-top left the page at y=${atTop}`);

await mobile.close();
await browser.close();

// --- console ---------------------------------------------------------------
if (errors.length === 0) {
  ok('no console errors');
} else {
  bad(`${errors.length} console error(s): ${errors.slice(0, 3).join(' | ')}`);
}

console.log(`\n${pass} ok, ${fail} failed`);
process.exit(fail > 0 ? 1 : 0);
