// Header: the pill condenses once the page scrolls, the desktop mega menus open
// on hover and on click, and the mobile drawer holds the same tree as accordions.
//
// Behaviour measured on the reference build: the condensed state starts past
// 20px of scroll; menus open on hover, stay open on click, close on an outside
// click; the drawer closes when the viewport leaves the mobile breakpoint.

const MOBILE_QUERY = '(max-width: 991px)';
const CONDENSED_AT = 20;

export function initHeader() {
  const header = document.querySelector('[data-site-header]');
  if (!header) return;

  initCondensedState(header);
  initMegaMenus(header);
  initMobileDrawer(header);
}

function initCondensedState(header) {
  let queued = false;

  const apply = () => {
    queued = false;
    header.dataset.scrolled = window.scrollY > CONDENSED_AT ? 'true' : 'false';
  };

  window.addEventListener(
    'scroll',
    () => {
      if (queued) return;
      queued = true;
      requestAnimationFrame(apply);
    },
    { passive: true },
  );

  apply();
}

/** @returns {{ trigger: HTMLElement, panel: HTMLElement }[]} */
function menuPairs(header) {
  return [...header.querySelectorAll('[data-nav-item]')].flatMap((item) => {
    const trigger = item.querySelector('[data-menu-trigger]');
    const panel = item.querySelector('[data-menu-panel]');
    return trigger && panel ? [{ trigger, panel }] : [];
  });
}

function setMenuOpen({ trigger, panel }, open) {
  panel.hidden = !open;
  trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  trigger.closest('[data-nav-item]').dataset.open = open ? 'true' : 'false';
}

function initMegaMenus(header) {
  const pairs = menuPairs(header);
  if (pairs.length === 0) return;

  const closeOthers = (keep) => pairs.filter((pair) => pair !== keep).forEach((pair) => setMenuOpen(pair, false));

  for (const item of header.querySelectorAll('[data-nav-item]')) {
    const pair = pairs.find((candidate) => item.contains(candidate.trigger));
    if (!pair) continue;

    item.addEventListener('mouseenter', () => {
      closeOthers(pair);
      setMenuOpen(pair, true);
    });
    item.addEventListener('mouseleave', () => setMenuOpen(pair, false));
    // Clicking the trigger holds the menu open; a second click is not a close.
    pair.trigger.addEventListener('click', () => {
      closeOthers(pair);
      setMenuOpen(pair, true);
    });
  }

  document.addEventListener('click', (event) => {
    if (header.contains(event.target)) return;
    pairs.forEach((pair) => setMenuOpen(pair, false));
  });
}

function initMobileDrawer(header) {
  const toggle = header.querySelector('[data-drawer-toggle]');
  const drawer = header.querySelector('[data-mobile-drawer]');
  if (!toggle || !drawer) return;

  const setOpen = (open) => {
    drawer.hidden = !open;
    toggle.dataset.open = open ? 'true' : 'false';
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  toggle.addEventListener('click', () => setOpen(drawer.hidden));

  for (const accordion of drawer.querySelectorAll('[data-drawer-accordion]')) {
    const sublist = accordion.nextElementSibling;
    if (!sublist?.matches('[data-drawer-sublist]')) continue;

    accordion.addEventListener('click', () => {
      const open = sublist.hidden;
      sublist.hidden = !open;
      accordion.dataset.open = open ? 'true' : 'false';
      accordion.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  document.addEventListener('click', (event) => {
    if (!drawer.hidden && !header.contains(event.target)) setOpen(false);
  });

  window.matchMedia(MOBILE_QUERY).addEventListener('change', (event) => {
    if (!event.matches) setOpen(false);
  });
}
