<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Ai\AnalysisFailed;
use App\Services\Ai\AttemptAnalyzer;
use App\Services\Ai\AttemptTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asking for an AI analysis of a finished sitting.
 *
 * It is a POST and not a link on purpose: every generation spends money, so it
 * has to be something the participant asked for, with a CSRF token, and the
 * result page can then be reloaded as often as they like for free.
 *
 * The three refusals each end where the participant can act: someone else's
 * sitting is a 403, a sitting that is still running is sent back to the room
 * exactly as `ResultController` does, and a gateway that failed leaves an
 * Indonesian line on the result page rather than a stack trace.
 */
final class AnalysisController extends Controller
{
    public function store(Request $request, ExamAttempt $attempt, AttemptAnalyzer $analyzer): RedirectResponse|JsonResponse
    {
        // A sitting is private. `{attempt}` is a bare id, so binding it is not
        // enough: another participant who guesses the number must not be able to
        // spend this account's gateway budget on it.
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if ($attempt->status->isOpen()) {
            return $this->refuseAnOpenSitting($request, $attempt);
        }

        try {
            $analysis = $analyzer->analyze($attempt, $request->boolean('ulangi'));
        } catch (AnalysisFailed $failure) {
            // Expected and already phrased for a participant, so it is not a bug
            // report: it travels as the page's flash message and nothing more.
            return $this->failure($request, $attempt, $failure->getMessage());
        } catch (Throwable $failure) {
            // Anything else is a bug or a missing table. The participant gets one
            // honest Indonesian sentence, this one gets the log.
            report($failure);

            return $this->failure($request, $attempt, 'Analisa AI gagal dibuat karena masalah di server. Coba lagi sebentar lagi.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'content' => $analysis->content,
                'model' => $analysis->model,
                'generated_at' => $analysis->created_at?->toIso8601String(),
            ]);
        }

        return redirect()->route('hasil.show', $attempt);
    }

    /**
     * A question asked back about the analysis of this sitting.
     *
     * The refusals are the same three the generation has, and each ends where the
     * participant can act: someone else's sitting is a 403, a sitting still
     * running goes back to the room, and a gateway that failed leaves an
     * Indonesian line on the page instead of a stack trace. There is no JSON
     * branch: the only caller is the form on the result page, and that page is
     * reloaded for free.
     */
    public function followUp(Request $request, ExamAttempt $attempt, AttemptTutor $tutor): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if ($attempt->status->isOpen()) {
            return redirect()->route('tryout.room', $attempt);
        }

        // The box is only rendered on a result page where the feature is
        // configured and migrated, so a request from anywhere else is asking for
        // something this checkout does not have.
        abort_unless($tutor->isReady(), 404);

        $message = trim((string) $request->input('message', ''));

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Tulis dulu pertanyaanmu tentang analisa ini.',
            ]);
        }

        if (mb_strlen($message) > AttemptTutor::MAX_MESSAGE_CHARS) {
            throw ValidationException::withMessages([
                'message' => 'Pertanyaanmu terlalu panjang. Maksimal '.AttemptTutor::MAX_MESSAGE_CHARS.' karakter.',
            ]);
        }

        try {
            $tutor->ask($attempt, $message);
        } catch (AnalysisFailed $failure) {
            // Expected and already phrased for a participant — including "there is
            // no analysis yet" — so it is not a bug report: it travels as the
            // page's own message and nothing more.
            return $this->backToAnalysis($attempt)->with('error', $failure->getMessage());
        } catch (Throwable $failure) {
            // Anything else is a bug or a missing table. The participant gets one
            // honest Indonesian sentence, this one gets the log.
            report($failure);

            return $this->backToAnalysis($attempt)->with('error', 'Pertanyaanmu gagal dikirim karena masalah di server. Coba lagi sebentar lagi.');
        }

        return $this->backToAnalysis($attempt);
    }

    /** Back to the analysis on the result page, where the new turn now is. */
    private function backToAnalysis(ExamAttempt $attempt): RedirectResponse
    {
        return redirect()->route('hasil.show', $attempt)->withFragment('analisa');
    }

    private function refuseAnOpenSitting(Request $request, ExamAttempt $attempt): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => AnalysisFailed::openSitting()->getMessage(),
                'attempt' => $attempt->id,
            ], 409);
        }

        return redirect()->route('tryout.room', $attempt);
    }

    private function failure(Request $request, ExamAttempt $attempt, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 502);
        }

        return redirect()->route('hasil.show', $attempt)->with('error', $message);
    }
}
