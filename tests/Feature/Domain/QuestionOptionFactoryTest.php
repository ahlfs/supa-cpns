<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The option factory has to be usable on its own: it must not collide with the five
 * options its parent already carries, and the option it makes has to match the
 * parent's grading shape — a key for TWK/TIU, a weight on the 1-5 ladder for TKP.
 */
final class QuestionOptionFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bare_factory_option_persists_on_its_key_graded_parent(): void
    {
        $option = QuestionOption::factory()->create();

        $this->assertTrue($option->exists);
        $this->assertSame(QuestionCategory::Twk, $option->question->category);
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F'], $option->question->options->pluck('label')->all());
        $this->assertFalse($option->is_correct);
        $this->assertNull($option->score);
    }

    public function test_a_factory_option_on_an_explicit_key_graded_parent_keeps_that_parent(): void
    {
        $question = Question::factory()->tiu()->create();

        $option = QuestionOption::factory()->create(['question_id' => $question->id]);

        $this->assertTrue($option->exists);
        $this->assertSame($question->id, $option->question_id);
        $this->assertSame(QuestionCategory::Tiu, $option->question->category);
        $this->assertFalse($option->is_correct);
        $this->assertNull($option->score);
        // The parent keeps exactly its own key; the extra option is a distractor.
        $this->assertSame([$question->correctOption()->label], $question->options()->where('is_correct', true)->pluck('label')->all());
    }

    public function test_a_factory_option_on_a_tkp_parent_carries_a_weight_and_no_key(): void
    {
        $question = Question::factory()->tkp()->create();

        $option = QuestionOption::factory()->create(['question_id' => $question->id]);

        $this->assertTrue($option->exists);
        $this->assertSame($question->id, $option->question_id);
        $this->assertSame(QuestionCategory::Tkp, $option->question->category);
        $this->assertNull($option->is_correct);
        $this->assertGreaterThanOrEqual(1, $option->score);
        $this->assertLessThanOrEqual(5, $option->score);
    }
}
