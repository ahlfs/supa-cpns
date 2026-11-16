<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Ai\QuestionTutor;
use App\Services\Exam\ScoreReport;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The page the participant came back for. Every number on it was frozen when the
 * sitting was graded, so this controller computes nothing: it reads the stored
 * scores, the answer rows and the subtopic rollup through `ScoreReport`.
 *
 * It also hands the page whatever conversations exist about individual items, in
 * one query rather than one per row, so the review list can print them as it
 * builds. Nothing is generated here: asking costs money and has to be asked for.
 */
final class ResultController extends Controller
{
    /**
     * The header a filter tab sends when it wants the review card's body alone.
     * It is a header rather than a route of its own so that the fragment's address
     * is the page's address: one place renders the list, and the whole-page and
     * fragment answers can never drift apart.
     */
    private const PARTIAL_HEADER = 'X-Review-Partial';

    private const FILTERS = ['all', 'wrong', 'blank', 'correct', 'answered'];

    public function show(
        Request $request,
        ExamAttempt $attempt,
        ContentRepository $content,
        QuestionTutor $tutor,
    ): View|RedirectResponse|Response {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        // No route may reveal a score before the sitting is submitted.
        if ($attempt->status->isOpen()) {
            return redirect()->route('tryout.room', $attempt);
        }

        $filter = $request->query('filter');
        $filter = is_string($filter) && in_array($filter, self::FILTERS, true) ? $filter : 'all';

        // Which item to open: set after a question is asked without scripting,
        // when the answer has to be visible on arrival. A number that matches no
        // item opens nothing, which is what the default does anyway.
        $open = $request->query('buka');
        $open = is_string($open) && ctype_digit($open) ? (int) $open : null;

        $report = new ScoreReport($attempt);

        $review = [
            'filter' => $filter,
            'open' => $open,
            'review' => $report->review($filter),
            'aiReady' => $tutor->isReady(),
            'chats' => $tutor->threadsFor($attempt),
        ];

        // The body of the review card, for the browser to swap in place so that
        // changing filter does not start the page again at the top. Every gate
        // above — ownership, a submitted sitting, the filter whitelist — has
        // already run, so the fragment cannot be reached more freely than the page.
        // With scripting off this header never arrives and the same request renders
        // the whole page, which is why the tabs are ordinary links.
        if ($request->header(self::PARTIAL_HEADER) === '1') {
            return response()->view('exam.partials.result-review', $review + ['attempt' => $attempt]);
        }

        return view('exam.result', [
            'site' => $content->site(),
            'attempt' => $attempt,
            'report' => $report,
            'subtests' => $report->subtests(),
            'subtopics' => $report->subtopics(),
            'timing' => $report->timing(),
            'history' => $report->history(),
            'chart' => $report->chart(),
        ] + $review + $content->chrome());
    }
}
