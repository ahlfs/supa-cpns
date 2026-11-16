<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Services\Exam\AnswerRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AnswerRecorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ExamAttempt, 1: array<int, Question>}
     */
    private function sitting(int $items = 3): array
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count($items)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $attempt = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        return [$attempt, $questions->all()];
    }

    public function test_clicking_the_same_option_twice_leaves_one_row(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);
        $option = $questions[0]->options->first();

        $recorder->record($attempt, $questions[0]->id, $option->id);
        $recorder->record($attempt->refresh(), $questions[0]->id, $option->id);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
        $this->assertSame($option->id, $attempt->refresh()->selectedOptionFor($questions[0]));
    }

    public function test_changing_the_option_updates_the_same_row(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);
        $first = $questions[0]->options->first();
        $second = $questions[0]->options->last();

        $recorder->record($attempt, $questions[0]->id, $first->id);
        $recorder->record($attempt->refresh(), $questions[0]->id, $second->id);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
        $this->assertSame($second->id, $attempt->refresh()->selectedOptionFor($questions[0]));
    }

    public function test_skipping_records_a_row_with_no_selection(): void
    {
        [$attempt, $questions] = $this->sitting();

        app(AnswerRecorder::class)->record($attempt, $questions[0]->id, null);

        $this->assertNull($attempt->refresh()->selectedOptionFor($questions[0]));
        $this->assertSame(0, $attempt->answeredCount());
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_an_item_from_another_package_is_refused(): void
    {
        [$attempt] = $this->sitting();
        $stranger = Question::factory()->twk()->create();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt, $stranger->id, $stranger->options->first()->id);
    }

    public function test_an_option_that_belongs_to_another_question_is_refused(): void
    {
        [$attempt, $questions] = $this->sitting();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt, $questions[0]->id, $questions[1]->options->first()->id);
    }

    public function test_a_finished_attempt_accepts_nothing(): void
    {
        [$attempt, $questions] = $this->sitting();
        $attempt->forceFill(['status' => AttemptStatus::Submitted])->save();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt->refresh(), $questions[0]->id, $questions[0]->options->first()->id);
    }

    public function test_a_late_write_closes_and_grades_the_sitting(): void
    {
        [$attempt, $questions] = $this->sitting();
        $attempt->forceFill([
            'started_at' => now()->subMinutes(101),
            'expires_at' => now()->subMinute(),
        ])->save();

        try {
            app(AnswerRecorder::class)->record($attempt->refresh(), $questions[0]->id, $questions[0]->options->first()->id);
            $this->fail('A late write should have been refused.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_a_flag_survives_an_option_change_and_is_only_cleared_explicitly(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);
        $first = $questions[0]->options->first();
        $second = $questions[0]->options->last();

        $recorder->record($attempt, $questions[0]->id, $first->id, flagged: true);

        $this->assertTrue($this->flagOf($attempt, $questions[0]->id));

        // The flag toggle and the option click are separate calls; an option click
        // says nothing about the flag, so it must leave it alone.
        $recorder->record($attempt->refresh(), $questions[0]->id, $second->id);

        $this->assertSame($second->id, $attempt->refresh()->selectedOptionFor($questions[0]));
        $this->assertTrue($this->flagOf($attempt, $questions[0]->id));

        // The opt-out path is still available when the flag is passed explicitly.
        $recorder->record($attempt->refresh(), $questions[0]->id, $second->id, flagged: false);

        $this->assertFalse($this->flagOf($attempt, $questions[0]->id));
    }

    private function flagOf(ExamAttempt $attempt, int $questionId): bool
    {
        return (bool) $attempt->refresh()->answers()->where('question_id', $questionId)->value('is_flagged');
    }

    public function test_timing_accrues_on_the_item_that_was_on_screen_and_is_capped(): void
    {
        // closeOpenInterval() reads the clock again and banks whole seconds
        // (getTimestamp() truncates). With the live clock the write of the 42
        // below and that read can straddle a second boundary, banking 43. Frozen,
        // both reads are the same instant so the intended 42 is exact.
        $this->freezeTime();

        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);

        $recorder->openQuestion($attempt, $questions[0]);
        $attempt->forceFill(['last_activity_at' => now()->subSeconds(42)])->save();
        $recorder->openQuestion($attempt->refresh(), $questions[1]);

        $this->assertSame(42, $attempt->refresh()->answers()->where('question_id', $questions[0]->id)->value('seconds_spent'));

        $attempt->forceFill(['last_activity_at' => now()->subHour()])->save();
        $recorder->closeOpenInterval($attempt->refresh());

        $this->assertSame(
            (int) config('cpns.timing_cap_seconds'),
            (int) $attempt->refresh()->answers()->where('question_id', $questions[1]->id)->value('seconds_spent'),
        );
    }
}
