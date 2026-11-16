// The review list: its filter tabs and its "Buka semua" control.
//
// The list is plain `<details>` and the tabs are plain links, so opening a row,
// reading it and filtering all work with scripting off — the server is the only
// thing that decides what a filter contains. What scripting adds is that a tab
// stops reloading the document. Following one made the browser build a new page
// and start it at the top, throwing away the reader's place in a 110-row list in
// order to show them a list they were already looking at.
//
// So a tab click is fetched as what it already is — the body of the review card —
// and swapped into the card, which is declared in the page and never replaced.
// Every listener below is bound once, to that card, and survives each swap: there
// is nothing to re-bind or re-initialise when new rows arrive.

const FRAGMENT_HEADER = 'X-Review-Partial';

export function initReview() {
  const root = document.querySelector('[data-review]');
  if (!root) return;

  const items = () => [...root.querySelectorAll('[data-review-item]')];

  // A filter can leave the list empty, and "Buka semua" over zero rows is noise,
  // so the control stays hidden rather than merely disabled.
  const syncToggle = () => {
    const toggle = root.querySelector('[data-review-toggle-all]');
    if (!toggle) return;

    const rows = items();
    const allOpen = rows.length > 0 && rows.every((item) => item.open);

    toggle.dataset.open = String(allOpen);
    toggle.textContent = allOpen ? 'Tutup semua' : 'Buka semua';
    toggle.hidden = rows.length === 0;
  };

  // Pressing tabs quickly can land the replies out of order. Only the newest
  // request is allowed to paint, so a slow first answer cannot overwrite the list
  // the reader actually asked for.
  let latest = 0;

  const swap = async (href, mode) => {
    const token = (latest += 1);

    root.dataset.loading = 'true';

    let html;

    try {
      const response = await fetch(href, {
        headers: { [FRAGMENT_HEADER]: '1' },
        credentials: 'same-origin',
      });

      if (!response.ok) throw new Error(String(response.status));

      html = await response.text();
    } catch {
      // The tabs are real links and the server is what filters, so a fragment that
      // cannot be fetched is not an error to report: follow the link and let the
      // browser do it the original way. The address bar is left alone precisely so
      // that this fallback is correct.
      if (token === latest) window.location.assign(href);

      return;
    }

    if (token !== latest) return;

    root.innerHTML = html;

    if (mode === 'push') history.pushState({ review: true }, '', href);

    // The tab that was pressed went out with the old body, so focus is put back on
    // the tab that is now current — otherwise a keyboard user is dropped at the
    // top of the document, which is the very thing this replaces. `preventScroll`
    // is what keeps the list where it is.
    root.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });

    syncToggle();
    root.dataset.loading = 'false';
  };

  // One listener on the card, because the controls inside it are re-rendered by
  // every swap.
  root.addEventListener('click', (event) => {
    // Leave modified clicks and middle-clicks to the browser: those mean "open
    // this elsewhere", and a tab is a real link so it can.
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const toggle = event.target.closest('[data-review-toggle-all]');

    if (toggle) {
      const openThem = toggle.dataset.open !== 'true';

      for (const item of items()) item.open = openThem;

      syncToggle();

      return;
    }

    const tab = event.target.closest('[data-review-tab]');
    if (!tab) return;
    if (tab.target && tab.target !== '_self') return;

    event.preventDefault();

    void swap(tab.href, 'push');
  });

  // `open` changes on a `<details>` do not bubble, but they are still dispatched
  // through the capture phase — so one capturing listener here covers the rows
  // that arrive later, instead of a listener per row that a swap would discard.
  root.addEventListener('toggle', syncToggle, true);

  // Back and forward move between the filters that were pushed. The document never
  // changed, so the list has to be re-fetched for whatever the address bar says.
  window.addEventListener('popstate', () => {
    if (document.body.contains(root)) void swap(window.location.href, 'none');
  });

  syncToggle();
}
