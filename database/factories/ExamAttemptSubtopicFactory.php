<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptSubtopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptSubtopic>
 */
class ExamAttemptSubtopicFactory extends Factory
{
    protected $model = ExamAttemptSubtopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'category' => 'twk',
            'subcategory' => 'pilar_negara',
            'correct' => 0,
            'wrong' => 0,
            'blank' => 0,
            'score' => 0,
        ];
    }
}
