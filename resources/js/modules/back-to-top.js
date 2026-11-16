// Floating "back to top" button: it appears past 600px of scroll (the threshold
// measured on the reference build) and scrolls home; the smooth motion comes from
// `scroll-behavior: smooth` on <html>.

const VISIBLE_AT = 600;

export function initBackToTop() {
  const button = document.querySelector('[data-back-to-top]');
  if (!button) return;

  let queued = false;

  const apply = () => {
    queued = false;
    button.dataset.visible = window.scrollY > VISIBLE_AT ? 'true' : 'false';
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

  button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  apply();
}
