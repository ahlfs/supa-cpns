// Print the geometry and computed style of an element subtree in a real browser.
// Used to compare a rebuilt section against the captured reference values.
//
//   node tools/probe-element.mjs <url> <selector> [--depth=N] [--match=regex]
//   node tools/probe-element.mjs http://127.0.0.1:8080/ '[data-section="stats"]' --depth=4
import { chromium } from 'playwright-core';

const [url, selector] = process.argv.slice(2);
const args = Object.fromEntries(
  process.argv.slice(2).filter((a) => a.startsWith('--')).map((a) => a.replace(/^--/, '').split('=')),
);
const depth = Number(args.depth ?? 99);
const match = args.match ? new RegExp(args.match, 'i') : null;
const width = Number(args.width ?? 1440);

const BROWSER = process.env.BROWSER_PATH ?? '/home/ahlfs/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome';

const browser = await chromium.launch({ executablePath: BROWSER, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : width === 768 ? 1024 : 900 } });
await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }).catch(() => {});
await page.waitForFunction(() => document.fonts.status === 'loaded', { timeout: 8000 }).catch(() => {});

const rows = await page.evaluate(({ selector, depth, pattern }) => {
  const match = pattern ? new RegExp(pattern, 'i') : null;
  const root = document.querySelector(selector);
  if (!root) return null;

  const PROPS = [
    'display', 'position', 'zIndex', 'width', 'height', 'minHeight', 'maxHeight', 'maxWidth',
    'gridTemplateColumns', 'gridTemplateRows', 'gap', 'rowGap', 'columnGap', 'flexDirection', 'alignItems',
    'justifyContent', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
    'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
    'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform', 'textAlign',
    'color', 'backgroundColor', 'borderTopWidth', 'borderTopColor', 'borderRadius',
    'boxShadow', 'transform', 'transition', 'opacity', 'objectFit', 'overflow', 'aspectRatio',
  ];

  const out = [];
  const walk = (el, level) => {
    const cs = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    const style = {};
    for (const property of PROPS) {
      const value = cs[property];
      if (value && value !== 'none' && value !== 'normal' && value !== 'auto' && value !== '0px' && value !== 'rgba(0, 0, 0, 0)') {
        style[property] = value;
      }
    }

    const name = `${el.tagName.toLowerCase()}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : ''}`;
    const text = el.children.length === 0 ? (el.textContent || '').trim().slice(0, 60) : '';

    const label = `${'  '.repeat(level)}<${name}>`;
    if (!match || match.test(name) || match.test(text)) {
      out.push([label, [Math.round(rect.x), Math.round(rect.y + window.scrollY), Math.round(rect.width), Math.round(rect.height)], style, text]);
    }

    if (level < depth) for (const child of el.children) walk(child, level + 1);
  };

  walk(root, 0);
  return out;
}, { selector, depth, pattern: args.match ?? null });

if (!rows) {
  console.log(`no element matched ${selector}`);
} else {
  for (const [label, box, style, text] of rows) {
    console.log(`${label} box=${box[2]}x${box[3]} @(${box[0]},${box[1]})${text ? ` "${text}"` : ''}`);
    if (args.styles !== 'false') console.log(`${' '.repeat(label.search(/\S/))}   ${JSON.stringify(style)}`);
  }
}

await browser.close();
process.exit(0);
