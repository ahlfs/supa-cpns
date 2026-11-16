// The "Program Belajar" tablist.
//
// Every tab's grid is rendered by the server and sits next to the active one
// with `hidden`; switching is a visibility toggle, so the whole page works
// without JavaScript and no markup is built client-side.

export function initProgramTabs() {
  const tablist = document.querySelector('[data-program-tabs]');
  if (!tablist) return;

  const tabs = [...tablist.querySelectorAll('[data-tab]')];
  const panels = new Map(
    [...document.querySelectorAll('[data-program-panel]')].map((panel) => [panel.dataset.programPanel, panel]),
  );

  const activate = (tab) => {
    for (const other of tabs) {
      const selected = other === tab;
      other.dataset.active = selected ? 'true' : 'false';
      other.setAttribute('aria-selected', String(selected));
      other.tabIndex = selected ? 0 : -1;
    }

    for (const [key, panel] of panels) {
      panel.hidden = key !== tab.dataset.tab;
    }
  };

  for (const [index, tab] of tabs.entries()) {
    tab.addEventListener('click', () => activate(tab));
    tab.addEventListener('keydown', (event) => {
      const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
      if (step === 0) return;
      event.preventDefault();
      const next = tabs[(index + step + tabs.length) % tabs.length];
      next.focus();
      activate(next);
    });
  }

  activate(tabs.find((tab) => tab.dataset.active === 'true') ?? tabs[0]);
}
