<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_item_is_created_with_its_five_labelled_options(): void
    {
        $question = Question::factory()->twk()->create();

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $question->options->pluck('label')->all());
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
        $this->assertSame(QuestionCategory::Twk, $question->category);
        $this->assertSame('nasionalisme', $question->subcategory);
    }

    public function test_a_tkp_item_carries_a_full_weight_ladder_and_no_key(): void
    {
        $question = Question::factory()->tkp()->create();

        $this->assertSame([1, 2, 3, 4, 5], $question->options->pluck('score')->sort()->values()->all());
        $this->assertSame(0, $question->options->whereNotNull('is_correct')->count());
        $this->assertNull($question->correctOption());
    }

    public function test_subcategory_labels_come_from_the_blueprint(): void
    {
        $question = Question::factory()->tiu()->create();

        $this->assertSame('Analogi Verbal', $question->subcategoryLabel());
    }

    /**
     * The fixture must not always key the same letter. A factory that keyed A on
     * every item would let an implementation that hard-codes A pass every test in
     * this suite, and would let a test "prove" a letter only the fixture chose.
     */
    public function test_a_factory_item_does_not_always_key_the_first_letter(): void
    {
        $keys = Question::factory()->twk()->count(5)->create()
            ->map(fn (Question $question): string => (string) $question->correctOption()?->label)
            ->unique()
            ->values()
            ->all();

        $this->assertGreaterThan(1, count($keys), 'Five items must not all key option A.');

        foreach ($keys as $key) {
            $this->assertContains($key, QuestionOption::LETTERS);
        }
    }
}
