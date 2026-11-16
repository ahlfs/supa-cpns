// Layout defect measurement shared by every script that needs numbers about a
// rendered page instead of an impression of one.
//
// Vision reviews and eyeballing both produce plausible-sounding claims that are
// frequently wrong; this module answers the same questions with measurements:
//
//   overlap   two visible elements whose boxes intersect, where neither is an
//             ancestor of the other. Decorative (`aria-hidden`) elements are
//             reported separately, because a prop may overlap on purpose.
//   overflow  an element painting past the viewport's right edge (horizontal
//             scroll) or past its own scroll container.
//   clipped   text cut off by an overflow-hidden box (scrollHeight >
//             clientHeight) — the classic "card grew, container didn't".
//   ragged    siblings in one flex/grid row with different heights.
//   rhythm    the vertical gaps between a page's top-level sections.

export const WIDTHS = {
  desktop: 1440,
  phone: 390,
};

/**
 * Load every lazy image and settle the page before measuring, and leave the page
 * at the very top.
 *
 * Returning to the top has to be instant and verified. The marketing pages set
 * `scroll-behavior: smooth`, so a plain `scrollTo(0, 0)` starts an animation and
 * returns immediately — every measurement then read a page still travelling
 * upward, which reported scrolled-under content as "hidden behind the fixed
 * header" and produced nonsense section gaps. The scroll position is now part of
 * the returned report so a caller can prove where it measured.
 */
export async function settlePage(page) {
  await page.evaluate(async () => {
    const root = document.documentElement;
    const declared = root.style.scrollBehavior;
    root.style.scrollBehavior = 'auto';

    for (const image of document.querySelectorAll('img[loading="lazy"]')) image.loading = 'eager';

    window.scrollTo(0, document.body.scrollHeight);
    await new Promise((resolve) => setTimeout(resolve, 400));

    await Promise.all([...document.images].map((image) => (image.complete
      ? null
      : new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      }))));

    window.scrollTo(0, 0);
    // Two frames: the first applies the scroll, the second lets layout settle.
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    root.style.scrollBehavior = declared;
  });

  // The measurement is only meaningful from the top of the document.
  await page.waitForFunction(() => window.scrollY === 0, { timeout: 5000 }).catch(() => {});
}

/**
 * Measure the page currently loaded in `page`. Returns plain data so callers can
 * aggregate several pages before printing anything.
 */
export async function measureLayout(page) {
  return page.evaluate(() => {
    const path = (node) => {
      const parts = [];
      for (let el = node; el && el.nodeType === 1 && parts.length < 3; el = el.parentElement) {
        let part = el.tagName.toLowerCase();
        if (el.dataset.section) part += `[${el.dataset.section}]`;
        else if (el.dataset.programPanel) part += `[panel=${el.dataset.programPanel}]`;
        else if (el.id) part += `#${el.id}`;
        else if (el.classList.length) part += `.${[...el.classList].slice(0, 2).join('.')}`;
        parts.unshift(part);
      }
      return parts.join(' > ');
    };

    /*
     * A closed disclosure hides everything but its summary. Chromium holds the
     * collapsed content with `content-visibility: hidden`, so the descendants
     * still report a real box and a closed review row then reads as 132 elements
     * piled on top of each other — the exact opposite of what the page does.
     * Nothing inside a closed `<details>` can be seen, so nothing inside it can
     * collide.
     */
    const inClosedDisclosure = (el) => {
      const details = el.closest('details');
      if (details === null || details.open) return false;
      const summary = details.querySelector(':scope > summary');

      return summary === null || !summary.contains(el);
    };

    // Only elements a person can see and that occupy real space.
    const all = [...document.querySelectorAll('body *')].filter((el) => {
      const style = getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') return false;
      if (Number(style.opacity) === 0) return false;
      if (inClosedDisclosure(el)) return false;
      const rect = el.getBoundingClientRect();
      return rect.width > 1 && rect.height > 1;
    });

    /*
     * A drawing is one element, not a pile of them. The primitives inside an
     * `<svg>` (a `circle` and a `polyline` making up a check mark) always
     * intersect each other, and reporting that buries the real collisions under
     * hundreds of rows that no one can act on.
     */
    const isDrawingPart = (el) => ['circle', 'ellipse', 'line', 'path', 'polygon', 'polyline', 'rect', 'g', 'use']
      .includes(el.tagName.toLowerCase()) && el.closest('svg') !== null;

    /** True when a scrollable ancestor keeps the element inside the viewport. */
    const heldByScroller = (el, viewportWidth) => {
      for (let node = el.parentElement; node && node !== document.body; node = node.parentElement) {
        const style = getComputedStyle(node);
        if (['auto', 'scroll'].includes(style.overflowX) && node.getBoundingClientRect().right <= viewportWidth + 1) {
          return true;
        }
      }
      return false;
    };

    const rectOf = (el) => {
      const rect = el.getBoundingClientRect();
      return {
        left: rect.left + window.scrollX,
        top: rect.top + window.scrollY,
        right: rect.right + window.scrollX,
        bottom: rect.bottom + window.scrollY,
        width: rect.width,
        height: rect.height,
      };
    };

    const sectionOf = (el) => el.closest('[data-section]')?.dataset.section ?? '(none)';

    /*
     * A collision only counts when both boxes are in normal flow. A `fixed`,
     * `sticky` or `absolute` layer is expected to sit on top of things — the
     * page header does exactly that — so treating those as overlap reports the
     * header against every section on the page and buries the real defect. What
     * cannot happen by accident is two static/relative boxes intersecting: that
     * takes a negative margin, a transform, or a child that grew past its
     * container, and it is precisely the "elements stacked on each other" case.
     */
    const inFlow = (el) => {
      for (let node = el; node && node !== document.body; node = node.parentElement) {
        const position = getComputedStyle(node).position;
        if (position === 'fixed' || position === 'absolute' || position === 'sticky') return false;
      }
      return true;
    };

    const candidates = all.filter((el) => inFlow(el) && !isDrawingPart(el));
    const overlap = [];
    const decorativeOverlap = [];

    for (let index = 0; index < candidates.length; index += 1) {
      const a = candidates[index];
      for (let other = index + 1; other < candidates.length; other += 1) {
        const b = candidates[other];
        if (a.contains(b) || b.contains(a)) continue;
        // Two elements only compete if they share a section — a section boundary
        // plus a negative margin is intentional geometry.
        if (sectionOf(a) !== sectionOf(b)) continue;
        // An icon nested inside the label it decorates is a layout, not a defect.
        if (a.parentElement === b.parentElement && (a.tagName === 'SVG' || b.tagName === 'SVG')) continue;

        const boxA = rectOf(a);
        const boxB = rectOf(b);
        const overlapWidth = Math.min(boxA.right, boxB.right) - Math.max(boxA.left, boxB.left);
        const overlapHeight = Math.min(boxA.bottom, boxB.bottom) - Math.max(boxA.top, boxB.top);
        if (overlapWidth <= 1 || overlapHeight <= 1) continue;

        const area = overlapWidth * overlapHeight;
        const smaller = Math.min(boxA.width * boxA.height, boxB.width * boxB.height);
        // Below 1% of the smaller box is sub-pixel noise from rounding.
        if (area / smaller < 0.01) continue;

        const entry = {
          section: sectionOf(a),
          overlap: `${Math.round(overlapWidth)}x${Math.round(overlapHeight)}px`,
          share: `${Math.round((area / smaller) * 100)}%`,
          area,
          a: path(a),
          b: path(b),
        };

        // A decorative layer on top of content is a judgement call, not a defect.
        // Content hidden behind content never is.
        if (a.getAttribute('aria-hidden') === 'true' || b.getAttribute('aria-hidden') === 'true') {
          decorativeOverlap.push(entry);
        } else {
          overlap.push(entry);
        }
      }
    }

    // Strongest collisions first, and drop the near-duplicates that a deep tree
    // produces (a parent and its child both colliding with the same box).
    overlap.sort((first, second) => second.area - first.area);
    const seen = new Set();
    const distinctOverlap = overlap.filter((item) => {
      const key = `${item.a}|${item.b}`;
      const loose = `${item.overlap}|${item.b}`;
      if (seen.has(key) || seen.has(loose)) return false;
      seen.add(key);
      seen.add(loose);
      return true;
    });

    const overflow = [];
    const clipped = [];
    const viewportWidth = document.documentElement.clientWidth;

    for (const el of all) {
      const box = rectOf(el);
      if (box.right > viewportWidth + 1 && !heldByScroller(el, viewportWidth)) {
        overflow.push({
          section: sectionOf(el),
          el: path(el),
          past: `${Math.round(box.right - viewportWidth)}px`,
          width: Math.round(box.width),
        });
      }

      const style = getComputedStyle(el);
      const hidesY = ['hidden', 'clip'].includes(style.overflowY);
      // A box that crops something aria-hidden is a deliberate crop — a thumbnail,
      // a photo bleeding off its frame — not text a person lost. Only content the
      // page still claims to show counts as clipped.
      const decorativeCrop = el.getAttribute('aria-hidden') === 'true'
        || el.closest('[aria-hidden="true"]') !== null;
      if (hidesY && !decorativeCrop && el.scrollHeight > el.clientHeight + 2 && el.clientHeight > 8) {
        clipped.push({
          section: sectionOf(el),
          el: path(el),
          content: el.scrollHeight,
          box: el.clientHeight,
        });
      }
    }

    /*
     * Siblings that flex/grid laid out in one ROW must share their edges — but
     * only where the row is a set of comparable blocks. A `li` inside a spaced
     * list is itself a flex row of a label, a paragraph and a badge: those differ
     * in height by design and are aligned on their baseline, so counting them
     * buried the real finding (two cards in a wrapping row) under hundreds of
     * false rows on the review page. A grid, or a wrapping flex row, is where an
     * uneven height actually reads as a mistake.
     */
    const ragged = [];
    const rows = new Map();

    for (const el of all) {
      const parent = el.parentElement;
      if (!parent) continue;
      const style = getComputedStyle(parent);
      const isGrid = style.display === 'grid';
      const isWrappingFlex = style.display === 'flex' && style.flexWrap !== 'nowrap';
      if (!isGrid && !isWrappingFlex) continue;
      const box = rectOf(el);
      // Card-sized blocks only: a caption beside a heading is not a card.
      if (box.height < 60) continue;
      const key = `${path(parent)}@${Math.round(box.top)}`;
      if (!rows.has(key)) rows.set(key, []);
      rows.get(key).push({ el, box });
    }

    for (const [key, items] of rows) {
      if (items.length < 2) continue;
      const tops = new Set(items.map((item) => Math.round(item.box.top)));
      if (tops.size !== 1) continue; // not the same row after all
      const heights = items.map((item) => Math.round(item.box.height));
      const spread = Math.max(...heights) - Math.min(...heights);
      if (spread > 4) {
        ragged.push({ row: key.split('@')[0], heights: heights.join(', '), spread: `${spread}px` });
      }
    }

    // Top-level sections and the white space between them.
    const sections = [...document.querySelectorAll('[data-section]')]
      // A sticky/fixed section (a page header, a floating action bubble) reports
      // the box it is pinned at, so its position says nothing about the flow and
      // it cannot be part of a gap measurement.
      .filter((el) => getComputedStyle(el).position === 'static' || getComputedStyle(el).position === 'relative')
      .sort((first, second) => rectOf(first).top - rectOf(second).top)
      .map((el) => ({ name: el.dataset.section, box: rectOf(el) }));

    const rhythm = sections.slice(1).map((section, index) => ({
      between: `${sections[index].name} -> ${section.name}`,
      gap: Math.round(section.box.top - sections[index].box.bottom),
    }));

    // Content hidden behind a fixed layer. A floating header is allowed to sit
    // on top of the page, but not on top of the content: the page has to reserve
    // the strip it occupies. Measuring this at scroll 0 is the only way to catch
    // it, because every other check compares boxes that move together.
    const occlusion = [];
    const fixedLayers = all.filter((el) => {
      const style = getComputedStyle(el);
      if (style.position !== 'fixed') return false;
      const box = rectOf(el);
      return box.height > 8 && box.height < window.innerHeight * 0.5 && box.width > 200;
    });

    for (const layer of fixedLayers) {
      const layerBox = layer.getBoundingClientRect();
      for (const el of all) {
        if (layer.contains(el) || el.contains(layer)) continue;
        if (el.getAttribute('aria-hidden') === 'true' || layer.getAttribute('aria-hidden') === 'true') continue;
        if (!inFlow(el)) continue;

        /*
         * A wrapper is not content. `main` spans the whole document and its box
         * therefore always intersects a header pinned at the top, which says
         * nothing about whether a person loses anything. Only an element that
         * carries its own text, or is itself a control or an image, can be
         * hidden in a way that matters.
         */
        const carriesText = [...el.childNodes]
          .some((node) => node.nodeType === 3 && node.textContent.trim().length > 0);
        const isControl = el.matches('a, button, input, select, textarea, img, svg');
        if (!carriesText && !isControl) continue;

        const box = el.getBoundingClientRect();
        const overlapWidth = Math.min(box.right, layerBox.right) - Math.max(box.left, layerBox.left);
        const overlapHeight = Math.min(box.bottom, layerBox.bottom) - Math.max(box.top, layerBox.top);
        if (overlapWidth <= 1 || overlapHeight <= 1) continue;

        // A sliver is a rounding artefact, not a hidden control.
        if (overlapHeight < 4) continue;

        occlusion.push({
          layer: path(layer),
          hidden: path(el),
          over: `${Math.round(overlapWidth)}x${Math.round(overlapHeight)}px`,
          text: (el.textContent ?? '').trim().slice(0, 40),
        });
      }
    }

    // One row per hidden element, strongest first: a fixed layer's own children
    // would otherwise report the same occlusion several times.
    const occlusionSeen = new Set();
    const distinctOcclusion = occlusion
      .sort((first, second) => Number.parseInt(second.over, 10) - Number.parseInt(first.over, 10))
      .filter((item) => {
        const key = item.hidden;
        if (occlusionSeen.has(key)) return false;
        occlusionSeen.add(key);
        return true;
      });

    return {
      scrollY: Math.round(window.scrollY),
      docWidth: document.documentElement.clientWidth,
      docHeight: document.documentElement.scrollHeight,
      scrollWidth: document.documentElement.scrollWidth,
      overlap: distinctOverlap,
      decorativeOverlap,
      occlusion: distinctOcclusion,
      overflow,
      clipped,
      ragged,
      rhythm,
      sections: sections.map((section) => `${section.name} ${Math.round(section.box.height)}px`),
    };
  });
}

export function countDefects(report) {
  return report.overlap.length + report.overflow.length + report.clipped.length
    + report.ragged.length + (report.occlusion?.length ?? 0);
}

/** Print one page's measurements in the shape the operator reads. */
export function printReport(label, report, { showAll = false } = {}) {
  const line = (text) => console.log(text);

  line(`\n== ${label}  (doc ${report.docWidth}x${report.docHeight}, scrollWidth ${report.scrollWidth}, scrollY ${report.scrollY ?? '?'})`);
  if (report.sections.length > 0) line(`sections: ${report.sections.join(' | ')}`);

  line(`\n-- content overlap (${report.overlap.length}, strongest first)`);
  for (const item of report.overlap.slice(0, 25)) {
    line(`  [${item.section}] ${item.overlap} ${item.share}\n      ${item.a}\n      × ${item.b}`);
  }
  if (report.overlap.length > 25) line(`  ... ${report.overlap.length - 25} more`);

  line(`\n-- horizontal overflow (${report.overflow.length})`);
  for (const item of report.overflow) line(`  [${item.section}] +${item.past}  w=${item.width}  ${item.el}`);

  line(`\n-- hidden behind a fixed layer (${report.occlusion?.length ?? 0})`);
  for (const item of report.occlusion ?? []) {
    line(`  ${item.over}  "${item.text}"\n      ${item.hidden}\n      behind ${item.layer}`);
  }

  line(`\n-- clipped text (${report.clipped.length})`);
  for (const item of report.clipped) line(`  [${item.section}] content ${item.content}px in ${item.box}px  ${item.el}`);

  line(`\n-- ragged rows (${report.ragged.length})`);
  for (const item of report.ragged) line(`  ${item.row}  heights ${item.heights}  spread ${item.spread}`);

  line(`\n-- section rhythm (${report.rhythm.length})`);
  for (const item of report.rhythm) line(`  ${item.between}: ${item.gap}px`);

  line(`\n-- decorative overlap (${report.decorativeOverlap.length}, informational)`);
  if (showAll) {
    for (const item of report.decorativeOverlap) line(`  [${item.section}] ${item.overlap}  ${item.a} × ${item.b}`);
  }

  line(`\nDEFECTS: ${countDefects(report)}`);
}
