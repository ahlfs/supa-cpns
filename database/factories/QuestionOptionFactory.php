<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
final class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            // The parent already carries its five A-E options, so an option made by
            // this factory takes the next free label. A caller that passes its own
            // question_id and label still wins.
            'label' => 'F',
            'body' => fake()->sentence(),
            'is_correct' => fn (array $attributes): ?bool => $this->parentRequiresKey($attributes) ? false : null,
            'score' => fn (array $attributes): ?int => $this->parentRequiresKey($attributes) ? null : 1,
        ];
    }

    /**
     * The parent's category decides the option's shape, and the enum is the only
     * thing allowed to answer that: a keyed item (TWK/TIU) grades on `is_correct`
     * and has no weight, a weighted item (TKP) has no key and takes a `score` from
     * the 1-5 ladder.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function parentRequiresKey(array $attributes): bool
    {
        return Question::findOrFail($attributes['question_id'])->category->requiresKey();
    }
}
