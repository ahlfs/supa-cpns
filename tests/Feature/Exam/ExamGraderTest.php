<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSubtopic;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\ExamGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ExamGraderTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private function paper(): ExamAttempt
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->create();

        return ExamAttempt::factory()->forUserAndPackage($user, $package)->create();
    }

    public function test_a_perfect_paper_scores_the_full_five_hundred_and_fifty(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(150, $graded->twk_score);
        $this->assertSame(175, $graded->tiu_score);
        $this->assertSame(225, $graded->tkp_score);
        $this->assertSame(550, $graded->total_score);
        $this->assertSame(AttemptStatus::Submitted, $graded->status);
        $this->assertNotNull($graded->submitted_at);
        $this->assertNull($graded->active_key);
        $this->assertTrue($graded->hasPassed());
    }

    public function test_wrong_answers_score_nothing_and_the_worst_tkp_choice_still_scores(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'worst');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(0, $graded->twk_score);
        $this->assertSame(0, $graded->tiu_score);
        $this->assertSame(45, $graded->tkp_score, 'TKP worst options score 1 each');
        $this->assertSame(45, $graded->total_score);
        $this->assertFalse($graded->hasPassed());
    }

    public function test_blank_answers_score_zero_including_tkp(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'blank');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(0, $graded->twk_score);
        $this->assertSame(0, $graded->tiu_score);
        $this->assertSame(0, $graded->tkp_score);
        $this->assertSame(0, $graded->total_score);
    }

    public function test_the_three_thresholds_are_independent_not_summed(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');
        $this->answerCorrectly($attempt, 'tiu', 14);

        // Wipe TIU and leave only 14 correct: 14 x 5 = 70, below the 80 threshold.
        $attempt->answers()
            ->whereIn('question_id', $attempt->package->questions()->where('questions.category', 'tiu')->pluck('questions.id'))
            ->whereNotIn('question_id', $attempt->package->questions()->where('questions.category', 'tiu')->limit(14)->pluck('questions.id'))
            ->update(['selected_option_id' => null]);

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(70, $graded->tiu_score);
        $this->assertSame(150, $graded->twk_score);
        $this->assertSame(225, $graded->tkp_score);
        $this->assertSame(445, $graded->total_score);
        $this->assertFalse($graded->hasPassed(), 'A 445 total still fails when one subtest is under its threshold');
    }

    public function test_the_frozen_result_lands_on_every_answer_row(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        app(ExamGrader::class)->grade($attempt);

        $twkAnswers = $attempt->answers()
            ->whereIn('question_id', $attempt->package->questions()->where('questions.category', 'twk')->pluck('questions.id'))
            ->get();

        $this->assertCount(30, $twkAnswers);
        $this->assertTrue($twkAnswers->every(fn ($answer): bool => $answer->is_correct === true));
        $this->assertTrue($twkAnswers->every(fn ($answer): bool => $answer->score_awarded === 5));
    }

    public function test_it_rolls_the_result_up_per_subtopic(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        app(ExamGrader::class)->grade($attempt);

        $subtopics = $attempt->subtopics()->get();

        $this->assertGreaterThan(0, $subtopics->count());
        $this->assertSame(
            $attempt->package->questions()->count(),
            (int) $subtopics->sum('correct') + (int) $subtopics->sum('wrong') + (int) $subtopics->sum('blank'),
        );
        $this->assertSame(550, (int) $subtopics->sum('score'));
        $this->assertSame(0, $subtopics->where('wrong', '>', 0)->count());
    }

    public function test_grading_twice_changes_nothing(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $first = app(ExamGrader::class)->grade($attempt);
        $rows = ExamAttemptSubtopic::query()->where('exam_attempt_id', $attempt->id)->count();
        $second = app(ExamGrader::class)->grade($first);

        $this->assertGreaterThan(0, $rows);
        $this->assertSame($first->total_score, $second->total_score);
        // A finished attempt is returned untouched, so the rollup is not rewritten.
        $this->assertSame($rows, ExamAttemptSubtopic::query()->where('exam_attempt_id', $attempt->id)->count());
    }

    public function test_an_expired_sitting_is_recorded_as_expired_not_submitted(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $graded = app(ExamGrader::class)->grade($attempt, expired: true);

        $this->assertSame(AttemptStatus::Expired, $graded->status);
        $this->assertSame(550, $graded->total_score);
    }

    public function test_a_keyed_item_with_no_marked_key_scores_zero_instead_of_throwing(): void
    {
        $attempt = $this->paper();
        $question = $attempt->package->questions()->where('questions.category', 'twk')->firstOrFail();

        // Nothing at the database level guarantees a TWK/TIU item has a key.
        $question->options()->update(['is_correct' => null]);
        $question->load('options');

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_id' => $question->options->first()->id,
            'answered_at' => now(),
        ]);

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(0, $graded->twk_score, 'A keyless item can never award the key score');
        $this->assertSame(
            0,
            $attempt->answers()->where('question_id', $question->id)->value('score_awarded'),
        );
    }

    public function test_tkp_is_weighted_not_keyed_so_the_per_correct_score_never_applies(): void
    {
        $attempt = $this->paper();
        $question = $attempt->package->questions()->where('questions.category', 'tkp')->firstOrFail();
        $middle = $question->options->firstWhere('score', 3);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_id' => $middle->id,
            'answered_at' => now(),
        ]);

        $graded = app(ExamGrader::class)->grade($attempt);

        // 3 is the chosen option's weight. A grader that fell through to
        // scorePerCorrect() would store 0 here, because TKP's key score is null.
        $this->assertSame(3, $attempt->answers()->where('question_id', $question->id)->value('score_awarded'));
        $this->assertSame(3, $graded->tkp_score);
        $this->assertSame(3, $graded->total_score, 'The other 109 items were left blank');

        $rollup = $attempt->subtopics()->where('category', QuestionCategory::Tkp->value)->get();
        $this->assertSame(3, (int) $rollup->sum('score'));
    }
}
