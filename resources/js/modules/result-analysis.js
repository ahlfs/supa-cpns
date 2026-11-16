// The AI analysis panel's in-flight state.
//
// Asking for an analysis is a plain form POST that reloads the result page, so the
// form works with scripting off and the server stays the one that decides what
// happened. What only the browser can do is say something while the request is out:
// a gateway answer can take a minute, and a button that looks untouched for a minute
// gets pressed again — which costs money a second time.
//
// So the module only flips the panel into its "sedang menganalisa" state, on the two
// forms that exist: the first generation and the deliberate regeneration.

export function initResultAnalysis() {
  const root = document.querySelector('[data-analysis]');
  if (!root) return;

  for (const form of root.querySelectorAll('[data-analysis-form]')) {
    form.addEventListener('submit', () => {
      const button = form.querySelector('[data-analysis-submit]');

      if (button) {
        button.disabled = true;
        button.dataset.state = 'working';
      }

      const status = form.querySelector('[data-analysis-status]');
      if (status) status.hidden = false;
    });
  }
}
