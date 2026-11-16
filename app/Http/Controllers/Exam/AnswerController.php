<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreAnswerRequest;
use App\Http\Requests\Exam\StorePositionRequest;
use App\Models\ExamAttempt;
use App\Services\Exam\AnswerRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The two writes the room makes. Each request names exactly one question, and the
 * response carries the freshly computed remaining seconds so a paused tab or a
 * changed system clock is pulled back into line within one click.
 *
 * There is no Save button: a tap is the save, so the same body arrives again and
 * again. That is why neither action writes a row itself — both hand the work to
 * `AnswerRecorder`, whose upsert on (attempt, question) is the single place a
 * duplicate request collapses onto one row.
 */
final class AnswerController extends Controller
{
    public function update(StoreAnswerRequest $request, ExamAttempt $attempt, AnswerRecorder $recorder): JsonResponse
    {
        $this->ensureOwner($request, $attempt);

        $validated = $request->validated();

        try {
            $recorder->record(
                $attempt,
                (int) $validated['question_id'],
                isset($validated['selected_option_id']) ? (int) $validated['selected_option_id'] : null,
                // Only a stated flag travels: `null` leaves the stored mark alone,
                // which is what keeps an option click from erasing "ragu-ragu".
                array_key_exists('is_flagged', $validated) ? (bool) $validated['is_flagged'] : null,
            );
        } catch (ValidationException $refusal) {
            return $this->refusal($refusal, $attempt);
        }

        return $this->state($attempt);
    }

    /**
     * Where the participant is. Moving is not an answer, so nothing is graded here
     * — the point is to close the timing interval on the item being left, which is
     * what lets the report say the hour went to item 12 rather than to item 88.
     */
    public function position(StorePositionRequest $request, ExamAttempt $attempt, AnswerRecorder $recorder): JsonResponse
    {
        $this->ensureOwner($request, $attempt);

        $question = $attempt->package->questions()
            ->whereKey((int) $request->validated('question_id'))
            ->first();

        if ($question !== null) {
            $recorder->openQuestion($attempt, $question);
        }

        return $this->state($attempt);
    }

    /**
     * The one answer the room needs, computed from the row rather than from the
     * request: the browser is optimistic and may have drawn a state the server
     * does not hold, and this is what corrects it.
     */
    private function state(ExamAttempt $attempt): JsonResponse
    {
        $fresh = $attempt->refresh();
        $total = $fresh->totalItems();
        $answered = $fresh->answeredCount();

        return response()->json([
            'ok' => true,
            'answered' => $answered,
            'blank' => $total - $answered,
            'remaining_seconds' => $fresh->remainingSeconds(),
        ]);
    }

    /**
     * A refusal the room has to act on, not only read.
     *
     * When the write is refused because the sitting is over, `record()` has
     * already graded it, keyed on `attempt`. Laravel's stock JSON error body
     * carries that under `errors`, but the room looks for it one level up,
     * beside `message` — a sitting that has been graded cannot be rescued by
     * tapping again, so the response hands the room the attempt and the page
     * reloads, which is what walks the participant out of the room. The error
     * body is therefore the one place this controller speaks to the browser
     * directly.
     */
    private function refusal(ValidationException $refusal, ExamAttempt $attempt): JsonResponse
    {
        $errors = $refusal->errors();

        $payload = [
            'ok' => false,
            'message' => $refusal->getMessage(),
            'errors' => $errors,
        ];

        if (array_key_exists('attempt', $errors)) {
            $payload['attempt'] = $attempt->id;
        }

        return response()->json($payload, 422);
    }

    /**
     * A sitting is private. `{attempt}` is a bare id, so binding it is not
     * enough: another participant who guesses or shares the number must not be
     * able to answer, move or end a paper that is not theirs.
     */
    private function ensureOwner(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
