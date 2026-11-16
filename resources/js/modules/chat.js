// The composer of a conversation with the assistant.
//
// Two conversations use it — the thread about one item of a sitting, and the
// conversation about that sitting's analysis — and both send the same way: Enter
// sends, Shift+Enter makes a line break, and the button stays for anybody who
// would rather press it.
//
// The exchange itself is a plain form post that redirects back to itself, so it
// works with scripting off and the server stays the only thing that renders a
// turn. What only the browser can do is say something while the answer is being
// written: a gateway reply can take a minute, and a button that looks untouched
// for a minute gets pressed again — which bills for a second answer nobody asked
// for.
//
// The suggestion chips are deliberately left enabled. They submit their own text
// under their own name, and a disabled control is dropped from the submitted
// data, so disabling the one that was pressed would send an empty question.

export function initChat() {
  for (const form of document.querySelectorAll('[data-chat-form]')) {
    const input = form.querySelector('[data-chat-input]');

    if (input) {
      input.addEventListener('keydown', (event) => {
        // `isComposing` is the input-method case: an Enter that is picking a
        // candidate is not a request to send anything.
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

        event.preventDefault();

        // requestSubmit rather than submit(): the browser's own validation and the
        // submit event both still run, so the state below is set by one path
        // instead of two.
        form.requestSubmit();
      });
    }

    form.addEventListener('submit', () => {
      const button = form.querySelector('[data-chat-submit]');
      if (button) button.disabled = true;

      const status = form.querySelector('[data-chat-status]');
      if (status) status.hidden = false;
    });
  }
}
