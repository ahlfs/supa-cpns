<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionCategory;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bank item. It belongs to the bank, not to a package, so one item serves
 * several tryouts and the admin never retypes it — which is also why freeing
 * package composition costs almost nothing.
 */
#[Fillable(['code', 'category', 'subcategory', 'stem', 'explanation', 'is_active'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => QuestionCategory::class,
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('label');
    }

    /**
     * @return BelongsToMany<ExamPackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ExamPackage::class, 'exam_package_question')
            ->withPivot('position');
    }

    public function correctOption(): ?QuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function subcategoryLabel(): string
    {
        return $this->category->subcategoryLabel($this->subcategory);
    }

    /**
     * Writes the item's five options and the grading shape that goes with them.
     *
     * This is the one home for what "graded" means for an item, and both writers —
     * the question form and the bulk importer — come through here, so the two can
     * never drift: a keyed item (TWK/TIU) marks exactly one option correct and
     * carries no weights at all, and a weighted item (TKP) marks none correct and
     * carries a weight on every option.
     *
     * An existing option is matched by its label, so saving an edit updates the
     * five rows in place instead of replacing ids the answers already point at.
     *
     * @param  array<string, array<string, mixed>>  $options  label => ['body' => …, 'score' => …]
     * @param  string|null  $key  null for a weighted item, else the correct label
     */
    public function syncOptions(array $options, ?string $key): void
    {
        foreach (QuestionOption::LETTERS as $letter) {
            $this->options()->updateOrCreate(
                ['label' => $letter],
                [
                    'body' => $options[$letter]['body'],
                    'is_correct' => $key === null ? null : $letter === $key,
                    'score' => $key === null ? (int) $options[$letter]['score'] : null,
                ],
            );
        }
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeCategory(Builder $query, QuestionCategory $category): void
    {
        $query->where('category', $category->value);
    }
}
