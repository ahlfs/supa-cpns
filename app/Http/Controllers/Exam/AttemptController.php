<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Exam\AttemptManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The room itself.
 *
 * The whole paper is rendered and the browser shows one item at a time, so
 * moving between items costs nothing and a lost connection cannot lose the
 * participant's place. The server stays the authority on the clock: the
 * countdown the browser draws starts from the remaining seconds computed here.
 */
final class AttemptController extends Controller
{
    public function show(Request $request, ExamAttempt $attempt, AttemptManager $manager): View|RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        $manager->expireOverdue($request->user());
        $attempt = $attempt->fresh();

        if ($attempt->isFinished()) {
            // A finished sitting has numbers to show, so it goes to the analysis
            // rather than back to the list.
            return redirect()->route('hasil.show', $attempt);
        }

        $questions = $attempt->package->questions()->with('options')->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $current = $attempt->current_question_id !== null
            ? $questions->firstWhere('id', $attempt->current_question_id)
            : null;

        return view('exam.room', [
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => $answers,
            'current' => $current ?? $questions->first(),
            'answered' => $attempt->answeredCount(),
            'remaining' => $attempt->remainingSeconds(),
        ]);
    }

    /**
     * The way out. The room disables its button while anything is blank, but that
     * is a courtesy, not the rule: the check lives in `AttemptManager::finish()`,
     * which refuses an incomplete paper and grades a sitting that ran out of time
     * as expired instead. That second path is the one this action exists for as
     * much as the first — at 00:00 the room posts whatever is on the paper and
     * bypasses its own guard, and a sitting nobody can end is never graded.
     */
    public function finish(Request $request, ExamAttempt $attempt, AttemptManager $manager): RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        $manager->finish($attempt);

        return redirect()->route('hasil.show', $attempt);
    }

    /**
     * A sitting is private: another participant's URL must not open it, whichever
     * of the two ids they guess.
     */
    private function ensureOwner(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
