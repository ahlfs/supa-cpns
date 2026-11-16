<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'exam_package_id', 'status', 'started_at', 'expires_at', 'submitted_at',
    'current_question_id', 'last_activity_at', 'twk_score', 'tiu_score', 'tkp_score',
    'total_score', 'active_key',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'twk_score' => 'int',
            'tiu_score' => 'int',
            'tkp_score' => 'int',
            'total_score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExamPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ExamPackage::class, 'exam_package_id');
    }

    /**
     * @return HasMany<ExamAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }

    /**
     * @return HasMany<ExamAttemptSubtopic, $this>
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(ExamAttemptSubtopic::class);
    }

    /**
     * The one written analysis of this sitting, if it was ever asked for. A
     * sitting has at most one, so this is a `hasOne` and not a history.
     *
     * @return HasOne<AttemptAnalysis, $this>
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(AttemptAnalysis::class, 'attempt_id');
    }

    /**
     * The conversations this participant has held about individual items of this
     * sitting. A sitting has one thread per item, so this is a history and not a
     * single row.
     *
     * @return HasMany<QuestionChat, $this>
     */
    public function questionChats(): HasMany
    {
        return $this->hasMany(QuestionChat::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    /**
     * Timestamp subtraction rather than Carbon::diffInSeconds(): the sign
     * convention changed in Carbon 3 and this is a timed exam.
     */
    public function remainingSeconds(): int
    {
        return max(0, $this->expires_at->getTimestamp() - now()->getTimestamp());
    }

    public function elapsedSeconds(): int
    {
        return max(0, now()->getTimestamp() - $this->started_at->getTimestamp());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->expires_at->getTimestamp() <= now()->getTimestamp();
    }

    public function isFinished(): bool
    {
        return ! $this->status->isOpen();
    }

    public function totalItems(): int
    {
        return $this->package->questions()->count();
    }

    public function answeredCount(): int
    {
        return $this->answers()->whereNotNull('selected_option_id')->count();
    }

    public function flaggedCount(): int
    {
        return $this->answers()->where('is_flagged', true)->count();
    }

    /** The chosen option id for one item, or null when it was skipped. */
    public function selectedOptionFor(Question $question): ?int
    {
        return $this->answers
            ->firstWhere('question_id', $question->id)
            ?->selected_option_id;
    }

    public function scoreFor(QuestionCategory $category): int
    {
        return (int) match ($category) {
            QuestionCategory::Twk => $this->twk_score,
            QuestionCategory::Tiu => $this->tiu_score,
            QuestionCategory::Tkp => $this->tkp_score,
        };
    }

    public function hasPassed(): bool
    {
        if (! $this->isFinished()) {
            return false;
        }

        foreach (QuestionCategory::cases() as $category) {
            if ($this->scoreFor($category) < $category->threshold()) {
                return false;
            }
        }

        return true;
    }
}
