// The exam room. One module drives both layouts, because there is only one set of
// elements: the desktop sidebar and the phone bottom sheet are the same navigator
// positioned by CSS at the 992px breakpoint.
//
// State lives in data attributes — `data-selected`, `data-status`, `data-state`,
// `data-open` — so what a state looks like stays in the Blade markup and this file
// only ever flips the attribute. Classes named in this file are picked up anyway,
// because resources/js is one of Tailwind's three @source lines.
//
// Saving is optimistic: the option recolours on click and the request goes out
// behind it. If the write fails, the colour goes back and a line says so, because
// a participant on a weak connection must never be told "saved" when it was not.

const WARNING_AT = 600; // ten minutes left
const DANGER_AT = 300; // five minutes left

export function initExam() {
  const root = document.querySelector('[data-exam]');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const answerUrl = root.dataset.answerUrl;
  const positionUrl = root.dataset.positionUrl;

  const panels = [...root.querySelectorAll('[data-exam-item]')];
  const cells = [...root.querySelectorAll('[data-exam-cell]')];
  const clocks = [...root.querySelectorAll('[data-exam-clock]')];
  const numbers = [...root.querySelectorAll('[data-exam-number]')];
  const answeredOut = [...root.querySelectorAll('[data-exam-answered]')];
  const blankOut = [...root.querySelectorAll('[data-exam-blank]')];
  // The line exists twice, in the item column and inside the sheet, because on a
  // phone the button that gets tapped is behind the sheet and a message the
  // participant cannot see is the same as no message.
  const statusLines = [...root.querySelectorAll('[data-exam-status]')];
  const sheet = root.querySelector('[data-exam-sheet]');
  const finishForms = [...root.querySelectorAll('[data-exam-finish]')];
  const finishButtons = [...root.querySelectorAll('[data-exam-finish-button]')];

  const total = Number(root.dataset.total ?? panels.length);
  let answered = Number(root.dataset.answered ?? 0);
  let remaining = Number(root.dataset.remaining ?? 0);
  let index = Math.max(0, cells.findIndex((cell) => cell.dataset.questionId === root.dataset.currentQuestionId));
  let leaving = false;
  let timedOut = false;
  let heartbeat = null;
  let statusTimer = null;

  const currentPanel = () => panels[index];
  const currentId = () => currentPanel()?.dataset.examItem;

  const say = (message, ok = true) => {
    if (statusLines.length === 0) return;
    statusLines.forEach((node) => {
      node.textContent = message;
      node.dataset.state = ok ? 'ok' : 'error';
    });
    clearTimeout(statusTimer);
    if (ok) {
      statusTimer = setTimeout(() => {
        statusLines.forEach((node) => { node.textContent = ''; });
      }, 1600);
    }
  };

  const renderCounts = () => {
    answeredOut.forEach((node) => { node.textContent = String(answered); });
    blankOut.forEach((node) => { node.textContent = String(Math.max(0, total - answered)); });

    // One policy on both faces. The desktop strip's copy and the sheet's copy are
    // the same action reached two ways, so they open and close together, and each
    // one stands beside the live blank count written above — a closed button next
    // to the number it is waiting on explains itself; a closed button alone is a
    // dead end.
    const ready = answered >= total;
    finishButtons.forEach((button) => { button.disabled = !ready; });
  };

  const renderClock = () => {
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    const text = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    const state = remaining <= DANGER_AT ? 'danger' : remaining <= WARNING_AT ? 'warning' : 'normal';

    clocks.forEach((node) => {
      node.textContent = text;
      node.dataset.state = state;
    });
  };

  const setCellStatus = (questionId, isAnswered, isFlagged) => {
    cells
      .filter((cell) => cell.dataset.questionId === String(questionId))
      .forEach((cell) => {
        cell.dataset.status = isAnswered ? 'answered' : 'blank';
        if (typeof isFlagged === 'boolean') cell.dataset.flag = isFlagged ? 'true' : 'false';
        const flag = cell.querySelector('[data-exam-cell-flag]');
        if (flag && typeof isFlagged === 'boolean') flag.classList.toggle('hidden', !isFlagged);
      });
  };

  const markCurrentCell = () => {
    cells.forEach((cell) => {
      cell.dataset.current = cell.dataset.questionId === String(currentId()) ? 'true' : 'false';
    });
  };

  const closeSheet = () => {
    if (sheet) sheet.dataset.open = 'false';
  };

  const put = async (url, body) => {
    const response = await fetch(url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) throw Object.assign(new Error('request failed'), { data });

    return data;
  };

  const applyServerState = (data) => {
    if (typeof data.answered === 'number') answered = data.answered;
    if (typeof data.remaining_seconds === 'number') remaining = data.remaining_seconds;
    renderCounts();
    renderClock();
  };

  const onSaveFailure = (error) => {
    // The sitting ran out of time behind the participant's back: the server has
    // already graded it, so walking them to the results page is the honest move.
    if (error?.data?.attempt) {
      leave();
      window.location.reload();
      return;
    }
    say(error?.data?.message ?? 'Gagal menyimpan. Periksa koneksi lalu coba lagi.', false);
  };

  const selectOption = async (button) => {
    const panel = button.closest('[data-exam-item]');
    const questionId = Number(panel.dataset.examItem);
    const previous = panel.querySelector('[data-exam-option][data-selected="true"]');

    if (previous === button) return;

    if (previous) previous.dataset.selected = 'false';
    button.dataset.selected = 'true';
    if (!previous) {
      answered += 1;
      setCellStatus(questionId, true);
      renderCounts();
    }

    try {
      applyServerState(await put(answerUrl, { question_id: questionId, selected_option_id: Number(button.dataset.optionId) }));
      say('Tersimpan');
    } catch (error) {
      button.dataset.selected = 'false';
      if (previous) {
        previous.dataset.selected = 'true';
      } else {
        answered -= 1;
        setCellStatus(questionId, false);
        renderCounts();
      }
      onSaveFailure(error);
    }
  };

  const skipCurrent = async () => {
    const panel = currentPanel();
    if (!panel) return;

    const questionId = Number(panel.dataset.examItem);
    const previous = panel.querySelector('[data-exam-option][data-selected="true"]');

    if (previous) {
      previous.dataset.selected = 'false';
      answered -= 1;
      setCellStatus(questionId, false);
      renderCounts();
    }

    try {
      applyServerState(await put(answerUrl, { question_id: questionId, selected_option_id: null }));
      say('Dilewati');
    } catch (error) {
      // The write never landed, so the server still holds the old answer. Put the
      // colour, the cell and the count back exactly as they were: without this the
      // screen claims a skip that never happened, shows a red box over an item the
      // server has marked, and the finish guard counts one blank too many.
      if (previous) {
        previous.dataset.selected = 'true';
        answered += 1;
        setCellStatus(questionId, true);
        renderCounts();
      }
      onSaveFailure(error);
    }

    goTo(index + 1);
  };

  const toggleFlag = async (button) => {
    const panel = currentPanel();
    if (!panel) return;

    const questionId = Number(panel.dataset.examItem);
    const flagged = button.dataset.flagged !== 'true';
    button.dataset.flagged = flagged ? 'true' : 'false';
    setCellStatus(questionId, cellIsAnswered(questionId), flagged);

    const selected = panel.querySelector('[data-exam-option][data-selected="true"]');

    try {
      applyServerState(await put(answerUrl, {
        question_id: questionId,
        selected_option_id: selected ? Number(selected.dataset.optionId) : null,
        is_flagged: flagged,
      }));
    } catch (error) {
      button.dataset.flagged = flagged ? 'false' : 'true';
      setCellStatus(questionId, cellIsAnswered(questionId), !flagged);
      onSaveFailure(error);
    }
  };

  const cellIsAnswered = (questionId) =>
    cells.some((cell) => cell.dataset.questionId === String(questionId) && cell.dataset.status === 'answered');

  const sendPosition = () => {
    clearTimeout(heartbeat);
    heartbeat = setTimeout(() => {
      put(positionUrl, { question_id: Number(currentId()) }).catch(() => {});
    }, 800);
  };

  function goTo(next) {
    if (next < 0 || next >= panels.length) return;

    panels[index]?.setAttribute('hidden', '');
    index = next;
    panels[index]?.removeAttribute('hidden');

    numbers.forEach((node) => { node.textContent = String(index + 1); });
    markCurrentCell();
    closeSheet();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    sendPosition();
  }

  const leave = () => {
    leaving = true;
    window.removeEventListener('beforeunload', warnBeforeLeaving);
  };

  const warnBeforeLeaving = (event) => {
    if (leaving) return;
    event.preventDefault();
    event.returnValue = '';
  };

  const autoFinish = () => {
    if (leaving) return;
    // The clock hit zero. From here the blank guard has nothing to say: a real
    // CAT submits the paper as it stands, so this forces the submit instead of
    // letting the guard veto it — otherwise the room sits at 00:00 with the clock
    // stopped and the page still taking taps, and the sitting is never graded.
    timedOut = true;
    leave();
    finishForms[0]?.requestSubmit();
  };

  const requestWakeLock = async () => {
    try {
      await navigator.wakeLock?.request('screen');
    } catch {
      // Unsupported, refused, or the tab is hidden. Nothing here is essential.
    }
  };

  // --- wiring -----------------------------------------------------------------

  root.addEventListener('click', (event) => {
    const option = event.target.closest('[data-exam-option]');
    if (option) return void selectOption(option);

    const flag = event.target.closest('[data-exam-flag]');
    if (flag) return void toggleFlag(flag);

    const cell = event.target.closest('[data-exam-cell]');
    if (cell) {
      const target = cells.indexOf(cell);
      if (target !== index) goTo(target);
      else closeSheet();
      return;
    }

    if (event.target.closest('[data-exam-prev]')) return void goTo(index - 1);
    if (event.target.closest('[data-exam-next]')) return void goTo(index + 1);
    if (event.target.closest('[data-exam-skip]')) return void skipCurrent();
    if (event.target.closest('[data-exam-sheet-toggle]')) {
      sheet.dataset.open = sheet.dataset.open === 'true' ? 'false' : 'true';
    }
  });

  finishForms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      // Time is up: whatever is on the paper is what gets graded, so the guard
      // below must not veto the auto-submit. A participant's tap on a normal
      // submit never sets this.
      if (timedOut) return;

      const blank = total - answered;

      if (blank > 0) {
        event.preventDefault();
        say(`Masih ada ${blank} soal yang belum dijawab.`, false);
        return;
      }

      leave();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.target instanceof HTMLElement && event.target.matches('input, textarea, select')) return;

    const key = event.key.toUpperCase();

    if (['A', 'B', 'C', 'D', 'E'].includes(key)) {
      const button = currentPanel()?.querySelector(`[data-exam-option][data-label="${key}"]`);
      if (button) {
        event.preventDefault();
        selectOption(button);
      }
      return;
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      goTo(index + 1);
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      goTo(index - 1);
    }
  });

  window.addEventListener('beforeunload', warnBeforeLeaving);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') requestWakeLock();
  });

  setInterval(() => {
    if (remaining <= 0) return;
    remaining -= 1;
    renderClock();
    if (remaining <= 0) autoFinish();
  }, 1000);

  renderCounts();
  renderClock();
  markCurrentCell();
  requestWakeLock();
  sendPosition();
}
