<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => QuestionCategory::Twk->value,
            'subcategory' => 'pilar_negara',
            'stem' => 'Soal '.fake()->unique()->numberBetween(1, 9_999_999),
            'explanation' => 'Pembahasan: '.fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Every item gets its five options at the moment it is created: A-E is not a
     * convention here, it is the shape of the exam, and a test or a seeder that
     * forgot them would produce an item nobody can answer.
     *
     * The set comes from `QuestionOption::LETTERS` — the single home for A-E, which
     * the controller, the request and the form already read from there — and the
     * key moves from row to row rather than always landing on the first letter. A
     * fixture that keyed A every time would let an implementation that hard-codes A
     * pass the whole suite, and would let a test "prove" a letter only the fixture
     * chose.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            $needsKey = $question->category->requiresKey();
            $key = QuestionOption::LETTERS[$question->id % count(QuestionOption::LETTERS)];

            foreach (QuestionOption::LETTERS as $index => $label) {
                $question->options()->create([
                    'label' => $label,
                    'body' => "Pilihan {$label} untuk soal #{$question->id}",
                    'is_correct' => $needsKey ? $label === $key : null,
                    'score' => $needsKey ? null : $index + 1,
                ]);
            }
        });
    }

    public function category(QuestionCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category->value,
            'subcategory' => (string) array_key_first($category->subcategories()),
        ]);
    }

    public function twk(): static
    {
        return $this->category(QuestionCategory::Twk);
    }

    public function tiu(): static
    {
        return $this->category(QuestionCategory::Tiu);
    }

    public function tkp(): static
    {
        return $this->category(QuestionCategory::Tkp);
    }
}
