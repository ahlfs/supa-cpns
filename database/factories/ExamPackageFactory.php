<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamPackage>
 */
class ExamPackageFactory extends Factory
{
    protected $model = ExamPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Tryout SKD '.fake()->unique()->numberBetween(1, 999_999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => 'Latihan SKD lengkap dengan analisa skor.',
            'duration_minutes' => (int) config('cpns.duration_minutes'),
            'status' => ExamPackage::STATUS_DRAFT,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ExamPackage::STATUS_DRAFT]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ExamPackage::STATUS_PUBLISHED]);
    }

    /**
     * A package that passes the composition rule: exactly 30/35/45 items.
     */
    public function complete(): static
    {
        return $this->afterCreating(function (ExamPackage $package): void {
            foreach (QuestionCategory::cases() as $category) {
                for ($i = 0; $i < $category->itemCount(); $i++) {
                    $package->addQuestion(Question::factory()->category($category)->create());
                }
            }
        });
    }
}
