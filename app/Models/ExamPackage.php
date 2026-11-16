<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'description', 'duration_minutes', 'status', 'opens_at', 'closes_at'])]
class ExamPackage extends Model
{
    /** @use HasFactory<ExamPackageFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'int',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_package_question')
            ->withPivot('position')
            ->orderBy('exam_package_question.position');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Once anyone has sat the package its composition is frozen, because two
     * sittings of different questions are not comparable on the history chart.
     * The items themselves stay editable everywhere (spec decision 13).
     */
    public function isLocked(): bool
    {
        return $this->attempts()->exists();
    }

    public function nextPosition(): int
    {
        return (int) $this->questions()->max('exam_package_question.position') + 1;
    }

    public function addQuestion(Question $question): void
    {
        $this->questions()->attach($question->id, ['position' => $this->nextPosition()]);
    }

    /**
     * The route key is the slug: package URLs read better than ids.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, mixed> */
    public function toContentArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
        ];
    }
}
