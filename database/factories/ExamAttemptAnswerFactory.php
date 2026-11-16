<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptAnswer>
 */
class ExamAttemptAnswerFactory extends Factory
{
    protected $model = ExamAttemptAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'question_id' => Question::factory(),
            'selected_option_id' => null,
            'seconds_spent' => fake()->numberBetween(5, 180),
            'is_flagged' => false,
            'answered_at' => now(),
        ];
    }
}
