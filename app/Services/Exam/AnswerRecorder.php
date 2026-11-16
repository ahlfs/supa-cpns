<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes one answer. Every call names exactly one question, so a lost, repeated or
 * retried request can only ever affect that item — the browser never uploads a
 * whole exam state blob and the server never accepts one.
 *
 * The upsert keyed on (attempt, question) is what makes clicking an option twice,
 * tapping fast on a slow connection, or retrying after a failure collapse onto a
 * single row instead of stacking duplicates.
 */
final class AnswerRecorder
{
    public function __construct(private readonly ExamGrader $grader) {}

    public function record(ExamAttempt $attempt, int $questionId, ?int $optionId, ?bool $flagged = null): ExamAttemptAnswer
    {
        if (! $attempt->status->isOpen()) {
            throw ValidationException::withMessages(['attempt' => 'Ujian ini sudah selesai.']);
        }

        if ($attempt->isOverdue()) {
            // A late write is not an error the participant can fix, so the sitting
            // is closed and graded on the spot rather than silently dropped.
            $this->grader->grade($attempt, expired: true);

            throw ValidationException::withMessages(['attempt' => 'Waktu ujian sudah habis.']);
        }

        $question = $attempt->package->questions()->whereKey($questionId)->first();

        if ($question === null) {
            throw ValidationException::withMessages(['question_id' => 'Soal itu bukan bagian dari paket ini.']);
        }

        if ($optionId !== null && ! $question->options()->whereKey($optionId)->exists()) {
            throw ValidationException::withMessages(['selected_option_id' => 'Opsi jawaban itu tidak dikenali.']);
        }

        $this->closeOpenInterval($attempt);

        return DB::transaction(function () use ($attempt, $question, $optionId, $flagged): ExamAttemptAnswer {
            $values = [
                'selected_option_id' => $optionId,
                'answered_at' => now(),
            ];

            // A null flag means the caller said nothing about it, so the stored
            // flag is left untouched: clicking an option, changing an answer or
            // retrying a slow request must never clear a participant's review mark.
            if ($flagged !== null) {
                $values['is_flagged'] = $flagged;
            }

            $answer = $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                $values,
            );

            $attempt->forceFill([
                'current_question_id' => $question->id,
                'last_activity_at' => now(),
            ])->save();

            return $answer;
        });
    }

    /**
     * The participant moved to another item without answering. The seconds already
     * spent still belong to the item they left, so the interval is closed here and
     * a new one opens on the item they are looking at.
     */
    public function openQuestion(ExamAttempt $attempt, Question $question): void
    {
        if (! $attempt->status->isOpen() || $attempt->isOverdue()) {
            return;
        }

        $this->closeOpenInterval($attempt);

        $attempt->forceFill([
            'current_question_id' => $question->id,
            'last_activity_at' => now(),
        ])->save();
    }

    /**
     * Adds the seconds since the last write to the item that was on screen. Capped,
     * because a tab left open overnight would otherwise hand one item half an hour
     * and make the timing report nonsense.
     */
    public function closeOpenInterval(ExamAttempt $attempt): void
    {
        $questionId = $attempt->current_question_id;
        $since = $attempt->last_activity_at;

        if ($questionId === null || $since === null) {
            return;
        }

        $seconds = min(
            max(0, now()->getTimestamp() - $since->getTimestamp()),
            (int) config('cpns.timing_cap_seconds'),
        );

        if ($seconds === 0) {
            return;
        }

        $answer = $attempt->answers()->firstOrNew(['question_id' => $questionId]);
        $answer->seconds_spent = (int) $answer->seconds_spent + $seconds;
        $answer->save();
    }
}
