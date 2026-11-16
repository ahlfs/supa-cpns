<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptSubtopic;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamAttemptSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_clock_starts_from_the_package_duration(): void
    {
        $package = ExamPackage::factory()->create(['duration_minutes' => 100]);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        $this->assertSame(6000, $attempt->expires_at->getTimestamp() - $attempt->started_at->getTimestamp());
        $this->assertSame(6000, $attempt->remainingSeconds());
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertFalse($attempt->isFinished());
    }

    /**
     * The package's duration_minutes is the clock; config is only the default a
     * new package is created with. The two values must differ here, otherwise the
     * assertion cannot tell a package-derived clock from a config-derived one.
     */
    public function test_the_clock_follows_the_package_duration_not_the_config_default(): void
    {
        $this->assertSame(100, (int) config('cpns.duration_minutes'), 'config default used by this test');

        $package = ExamPackage::factory()->create(['duration_minutes' => 130]);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        // 130 minutes = 7800s from the package. A config-derived clock would say 6000.
        $this->assertSame(7800, $attempt->expires_at->getTimestamp() - $attempt->started_at->getTimestamp());
        $this->assertSame(7800, $attempt->remainingSeconds());
    }

    public function test_an_overdue_attempt_reports_no_time_left_and_explains_itself(): void
    {
        $attempt = ExamAttempt::factory()->overdue()->create();

        $this->assertSame(0, $attempt->remainingSeconds());
        $this->assertTrue($attempt->isOverdue());
        $this->assertTrue($attempt->status->isOpen());
    }

    public function test_one_question_can_only_be_answered_once_per_attempt(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        $this->expectException(QueryException::class);

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);
    }

    /**
     * Attempt history is a record: the question bank cannot delete it out from
     * under a finished sitting. The foreign key restricts, so the delete is refused.
     */
    public function test_a_question_answered_in_an_attempt_cannot_be_deleted(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        $this->expectException(QueryException::class);

        $question->delete();
    }

    /**
     * A package's composition must not be mutated underneath a sitting either, so
     * the pivot row refuses to lose its question — ExamPackage::isLocked() is only
     * meaningful if composition cannot shrink after anyone has attempted it.
     */
    public function test_a_question_in_a_package_composition_cannot_be_deleted(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);

        $this->expectException(QueryException::class);

        $question->delete();
    }

    public function test_only_one_open_attempt_per_participant_and_package_is_possible(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->create([
            'active_key' => $user->id.':'.$package->id,
        ]);

        $this->expectException(QueryException::class);

        ExamAttempt::factory()->forUserAndPackage($user, $package)->create([
            'active_key' => $user->id.':'.$package->id,
        ]);
    }

    public function test_finished_attempts_do_not_block_a_retake(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create(['active_key' => null]);
        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create(['active_key' => null]);

        $this->assertSame(2, $user->attempts()->count());
    }

    /**
     * A fixture may not build a state the application cannot produce. The grader
     * writes `status` and all four score columns in one transaction, so a
     * `submitted` row with null scores was a shape this app never makes — and
     * because `scoreFor()` coerces the nulls through an int cast it read as a
     * finished paper of zeros: "gagal", with no numbers on it.
     */
    public function test_a_finished_fixture_carries_the_scores_the_grader_writes(): void
    {
        $attempt = ExamAttempt::factory()->submitted()->create();

        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);

        foreach (QuestionCategory::cases() as $category) {
            $this->assertNotNull(
                $attempt->getRawOriginal($category->value.'_score'),
                "A finished fixture must carry {$category->value}_score, as the grader writes it.",
            );
            $this->assertGreaterThanOrEqual(0, $attempt->scoreFor($category));
        }

        $this->assertSame(
            $attempt->twk_score + $attempt->tiu_score + $attempt->tkp_score,
            $attempt->total_score,
            'The four columns have to agree with each other.',
        );

        // A verdict the fixture states, rather than one a null cast invents.
        $this->assertTrue($attempt->hasPassed());

        // withScores() is how a test asks for a specific one, with the total
        // recomputed so the four columns cannot drift apart.
        $failed = ExamAttempt::factory()->submitted()->withScores(60, 80, 60)->create();

        $this->assertSame(200, $failed->total_score);
        $this->assertFalse($failed->hasPassed());
    }

    /**
     * The three subcategory vocabularies in `config/cpns.php` are independent, and
     * nothing enforced that their keys never collide. The unique key was
     * `(exam_attempt_id, subcategory)`, which encodes one global namespace for all
     * three — so a collision between, say, a TWK and a TIU key would surface as a
     * unique violation INSIDE the grader's transaction during submit: a 500 and an
     * attempt that can never be finished. The key is namespaced by category.
     */
    public function test_the_same_subcategory_key_may_recur_across_subtests(): void
    {
        $attempt = ExamAttempt::factory()->create();

        ExamAttemptSubtopic::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'category' => 'twk',
            'subcategory' => 'sama_di_dua_subtes',
        ]);

        ExamAttemptSubtopic::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'category' => 'tiu',
            'subcategory' => 'sama_di_dua_subtes',
        ]);

        $this->assertSame(2, $attempt->subtopics()->where('subcategory', 'sama_di_dua_subtes')->count());

        // Inside one subtest the same key is still a duplicate: the constraint has
        // not simply been dropped.
        $this->expectException(QueryException::class);

        ExamAttemptSubtopic::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'category' => 'twk',
            'subcategory' => 'sama_di_dua_subtes',
        ]);
    }

    public function test_answered_count_ignores_items_that_were_opened_but_not_answered(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(2)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'selected_option_id' => $questions[0]->options->first()->id,
        ]);
        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questions[1]->id,
            'selected_option_id' => null,
        ]);

        $this->assertSame(2, $attempt->totalItems());
        $this->assertSame(1, $attempt->answeredCount());
    }
}
