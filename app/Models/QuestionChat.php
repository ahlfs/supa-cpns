<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One conversation about one bank item, inside one finished sitting.
 *
 * It hangs off the sitting rather than the participant so the context is fixed:
 * what the participant chose on that paper is part of what the tutor is told,
 * and a second sitting of the same package starts its own thread instead of
 * inheriting an answer about a different attempt.
 */
#[Fillable(['exam_attempt_id', 'question_id'])]
class QuestionChat extends Model
{
    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Oldest first, which is the order a chat-completions request needs and the
     * order the thread is read on the page.
     *
     * @return HasMany<QuestionChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(QuestionChatMessage::class)->orderBy('id');
    }
}
