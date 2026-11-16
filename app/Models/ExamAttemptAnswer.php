<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_attempt_id', 'question_id', 'selected_option_id',
    'is_correct', 'score_awarded', 'seconds_spent', 'is_flagged', 'answered_at',
])]
class ExamAttemptAnswer extends Model
{
    /** @use HasFactory<ExamAttemptAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'bool',
            'score_awarded' => 'int',
            'seconds_spent' => 'int',
            'is_flagged' => 'bool',
            'answered_at' => 'datetime',
        ];
    }

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
     * @return BelongsTo<QuestionOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }

    public function isBlank(): bool
    {
        return $this->selected_option_id === null;
    }
}
