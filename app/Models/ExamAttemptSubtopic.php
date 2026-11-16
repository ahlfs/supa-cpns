<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionCategory;
use Database\Factories\ExamAttemptSubtopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_attempt_id', 'category', 'subcategory', 'correct', 'wrong', 'blank', 'score'])]
class ExamAttemptSubtopic extends Model
{
    /** @use HasFactory<ExamAttemptSubtopicFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => QuestionCategory::class,
            'correct' => 'int',
            'wrong' => 'int',
            'blank' => 'int',
            'score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function label(): string
    {
        return $this->category->subcategoryLabel($this->subcategory);
    }
}
