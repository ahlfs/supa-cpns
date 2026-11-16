<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Ai\AnalysisFailed;
use App\Services\Ai\QuestionTutor;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asking the tutor about one item of a finished sitting, and the page the asking
 * happens on.
 *
 * The page is its own route rather than a panel inside the result list: a
 * conversation needs room, and the result list is 110 rows of markup already.
 *
 * Every door is checked here, because this route spends money and writes rows.
 * The sitting has to be the caller's own, it has to be finished — asking mid-exam
 * would turn 110 questions into 110 conversations — and the item has to be one
 * this participant actually sat: package composition is what makes a question
 * reachable, so a guessed id opens nothing.
 *
 * The exchange is a plain form post that redirects back to itself, so the thread
 * on screen is always something the server rendered, and reloading it costs
 * nothing.
 */
final class QuestionChatController extends Controller
{
    /** The result page's own filters, so leaving here lands on the list you came from. */
    private const FILTERS = ['all', 'wrong', 'blank', 'correct', 'answered'];

    public function show(
        Request $request,
        ExamAttempt $attempt,
        Question $question,
        QuestionTutor $tutor,
        ContentRepository $content,
    ): View|RedirectResponse {
        $refusal = $this->refuse($request, $attempt, $question, $tutor);

        if ($refusal !== null) {
            return $refusal;
        }

        return view('exam.question', [
            'site' => $content->site(),
            'attempt' => $attempt,
            'question' => $question,
            'answer' => $attempt->answers->firstWhere('question_id', $question->id),
            'number' => $this->position($attempt, $question),
            'filter' => $this->filter($request),
            'messages' => $tutor->threadFor($attempt, $question)?->messages ?? collect(),
            'suggestions' => $this->suggestions($question),
        ] + $content->chrome());
    }

    public function store(
        Request $request,
        ExamAttempt $attempt,
        Question $question,
        QuestionTutor $tutor,
    ): RedirectResponse {
        $refusal = $this->refuse($request, $attempt, $question, $tutor);

        if ($refusal !== null) {
            return $refusal;
        }

        $back = $this->backToThread($request, $attempt, $question);

        // A suggestion chip submits its own text under its own name, so which
        // question was meant is never in doubt: when a chip was pressed it is the
        // one to ask, because pressing it was the later and deliberate act. The
        // participant's typed question arrives on its own name and is used when
        // no chip was involved.
        $message = trim((string) ($request->input('saran') ?: $request->input('message') ?: ''));

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Tulis dulu pertanyaanmu, atau pilih salah satu pertanyaan yang disarankan.',
            ]);
        }

        if (mb_strlen($message) > QuestionTutor::MAX_MESSAGE_CHARS) {
            throw ValidationException::withMessages([
                'message' => 'Pertanyaanmu terlalu panjang. Maksimal '.QuestionTutor::MAX_MESSAGE_CHARS.' karakter.',
            ]);
        }

        try {
            $tutor->ask($attempt, $question, $message);
        } catch (AnalysisFailed $failure) {
            // Expected and already phrased for a participant, so it is not a bug
            // report: it travels as the page's own message and nothing more.
            return $back->with('error', $failure->getMessage());
        } catch (Throwable $failure) {
            // Anything else is a bug or a missing table. The participant gets one
            // honest Indonesian sentence, this one gets the log.
            report($failure);

            return $back->with('error', 'Pertanyaanmu gagal dikirim karena masalah di server. Coba lagi sebentar lagi.');
        }

        return $back;
    }

    /**
     * Everything that must hold before this route does anything, in the order the
     * participant would hit it. Returns the response for a refusal, or null when
     * the request may go on.
     */
    private function refuse(
        Request $request,
        ExamAttempt $attempt,
        Question $question,
        QuestionTutor $tutor,
    ): ?RedirectResponse {
        // A sitting is private. `{attempt}` is a bare id, so binding it is not
        // enough: another participant who guesses the number must not be able to
        // spend this account's gateway budget on it.
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if ($attempt->status->isOpen()) {
            return redirect()->route('tryout.room', $attempt);
        }

        // The page is only linked from a result page where the feature is
        // configured and migrated, so a request from anywhere else is asking for
        // something this checkout does not have.
        abort_unless($tutor->isReady(), 404);

        // Reachable means "was on this paper", not "exists in the bank".
        abort_unless($this->isOnThePaper($attempt, $question), 404);

        return null;
    }

    private function isOnThePaper(ExamAttempt $attempt, Question $question): bool
    {
        return $attempt->package->questions()->whereKey($question->id)->exists();
    }

    /** Where the item sits on the paper: 1 to 110, the number the participant saw. */
    private function position(ExamAttempt $attempt, Question $question): ?int
    {
        return $attempt->package->questions()->whereKey($question->id)->first()?->pivot->position;
    }

    private function filter(Request $request): string
    {
        $filter = $request->input('filter');

        return is_string($filter) && in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    /**
     * Back to this same page, with the filter still on it, so leaving afterwards
     * returns to the list the participant was reading.
     */
    private function backToThread(Request $request, ExamAttempt $attempt, Question $question): RedirectResponse
    {
        return redirect()->route('hasil.tanya', [
            'attempt' => $attempt,
            'question' => $question,
            'filter' => $this->filter($request),
        ]);
    }

    /**
     * What a participant usually wants to ask, by subtest. TKP's questions are
     * not the keyed ones': every option there is defensible, and what actually
     * puzzles people is why the same sentence earns 2 one way and 5 the other.
     *
     * @return list<string>
     */
    private function suggestions(Question $question): array
    {
        return $question->category->requiresKey()
            ? ['Kenapa jawabanku salah?', 'Jelaskan konsep yang diuji soal ini', 'Kenapa opsi yang lain salah?']
            : ['Kenapa bobot opsi ini beda-beda?', 'Bagaimana memilih yang paling sesuai nilai ASN?'];
    }
}
