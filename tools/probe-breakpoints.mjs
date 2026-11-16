// Find the breakpoints a reference element actually changes at.
//
// Sweeps viewport widths, reads the computed style of the element matched by the
// selector (relative to the root element given), and prints only the widths where
// at least one watched property changes. That turns "which media query applies
// here" into a measurement instead of a reading of minified CSS.
//
// Usage (defaults to the captured reference build):
//   node tools/probe-breakpoints.mjs --selector='.asn-home__stat' --root='.asn-home__stats'
//   node tools/probe-breakpoints.mjs --selector='.asn-home__stat strong' --widths=1440,1024,768,640,390
//   node tools/probe-breakpoints.mjs --url=http://127.0.0.1:8080/ --root='[data-section="stats"]' --selector='div'
import { chromium } from 'playwright-core';

const args = Object.fromEntries(
  process.argv.slice(2).map((arg) => {
    const [key, ...rest] = arg.replace(/^--/, '').split('=');
    return [key, rest.join('=') || 'true'];
  }),
);

const URL = args.url ?? 'http://127.0.0.1:8081/index.html';
const ROOT = args.root ?? null;
const SELECTOR = args.selector ?? '*';
const WIDTHS = (args.widths ?? '1440,1366,1280,1200,1100,1024,991,900,860,768,767,700,640,600,480,414,390,360').split(',').map(Number);
const BROWSER = process.env.BROWSER_PATH ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome';

const PROPS = (args.props ?? [
  'display', 'flexDirection', 'flexWrap', 'alignItems', 'justifyContent', 'gap', 'gridTemplateColumns',
  'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginBottom',
  'fontSize', 'fontWeight', 'lineHeight', 'textAlign', 'width', 'height', 'minHeight', 'maxWidth',
  'backgroundColor', 'color', 'borderRadius', 'borderTopWidth', 'borderTopColor', 'boxShadow', 'position', 'opacity',
]).split(',');

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const page = await browser.newPage();

const snapshots = [];

for (const width of WIDTHS) {
  await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 }).catch(() => {});
  await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 6000 }).catch(() => {});

  const snapshot = await page.evaluate(({ root, selector, props }) => {
    const scope = root ? document.querySelector(root) : document;
    if (!scope) return { error: `root not found: ${root}` };

    const nodes = [...scope.querySelectorAll(selector)];
    if (nodes.length === 0) return { error: `selector matched nothing: ${selector}` };

    return nodes.slice(0, 2).map((node) => {
      const computed = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      const values = { box: `${Math.round(rect.width)}x${Math.round(rect.height)}` };
      for (const property of props) values[property] = computed[property];
      return values;
    });
  }, { root: ROOT, selector: SELECTOR, props: PROPS });

  snapshots.push({ width, snapshot });
}

await browser.close();

if (snapshots[0].snapshot.error) {
  console.log('error:', snapshots[0].snapshot.error);
  process.exit(1);
}

const first = snapshots[0].snapshot[0];
const keys = Object.keys(first);

console.log(`root=${ROOT ?? 'document'} selector=${SELECTOR} url=${URL}\n`);
console.log(`  ${'width'.padEnd(7)}${keys.map((k) => k.padEnd(22)).join('')}`);

let previous = null;
for (const { width, snapshot } of snapshots) {
  const current = snapshot[0];
  const changed = previous ? keys.filter((k) => current[k] !== previous[k]) : keys;
  if (previous && changed.length === 0) continue;

  console.log(`  ${String(width).padEnd(7)}${keys.map((k) => String(current[k] ?? '').padEnd(22)).join('')}`);
  if (previous) console.log(`         changed: ${changed.join(', ')}`);
  previous = current;
}
