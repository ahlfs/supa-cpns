<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class AttemptManagerTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private function manager(): AttemptManager
    {
        return app(AttemptManager::class);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_starting_creates_a_sitting_that_expires_in_one_hundred_minutes(): void
    {
        // The clock is frozen: reading now() once for the expiry and once for the
        // remaining seconds can otherwise straddle a second boundary and flake.
        $this->freezeTime();

        $attempt = $this->manager()->startOrResume($this->user(), ExamPackage::factory()->create());

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(6000, $attempt->remainingSeconds());
        $this->assertNotNull($attempt->active_key);
    }

    public function test_the_sitting_clock_comes_from_the_package_not_from_config(): void
    {
        $this->freezeTime();

        $package = ExamPackage::factory()->create(['duration_minutes' => 130]);

        $attempt = $this->manager()->startOrResume($this->user(), $package);

        $this->assertSame(7800, $attempt->remainingSeconds());
    }

    public function test_starting_again_resumes_the_same_sitting(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->create();

        $first = $this->manager()->startOrResume($user, $package);
        $second = $this->manager()->startOrResume($user, $package);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ExamAttempt::query()->count());
    }

    public function test_a_resumed_sitting_keeps_the_clock_it_was_given(): void
    {
        $this->freezeTime();

        $user = $this->user();
        $package = ExamPackage::factory()->create();

        $first = $this->manager()->startOrResume($user, $package);
        $first->forceFill(['started_at' => now()->subMinutes(30), 'expires_at' => now()->addMinutes(70)])->save();

        $resumed = $this->manager()->startOrResume($user, $package);

        $this->assertSame(4200, $resumed->remainingSeconds());
    }

    public function test_restarting_closes_the_old_sitting_as_expired_and_opens_a_new_one(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $old = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($old, 'best');

        $fresh = $this->manager()->restart($user, $package);

        $this->assertFalse($old->is($fresh));
        $this->assertSame(AttemptStatus::Expired, $old->refresh()->status);
        $this->assertSame(550, $old->total_score);
        $this->assertSame(AttemptStatus::InProgress, $fresh->status);
        $this->assertSame(0, $fresh->answeredCount());
        $this->assertSame(2, ExamAttempt::query()->count());
    }

    public function test_finishing_is_refused_while_one_item_is_blank(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $blank = $attempt->answers()->whereNotNull('selected_option_id')->first();
        $blank->forceFill(['selected_option_id' => null])->save();

        try {
            $this->manager()->finish($attempt->refresh());
            $this->fail('Finishing with a blank item should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1 soal', $exception->getMessage());
        }

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }

    public function test_finishing_a_complete_sitting_grades_it(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $finished = $this->manager()->finish($attempt->refresh());

        $this->assertSame(AttemptStatus::Submitted, $finished->status);
        $this->assertSame(550, $finished->total_score);
        $this->assertNull($finished->active_key);
    }

    public function test_an_incomplete_sitting_that_ran_out_of_time_is_graded_as_expired(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerCorrectly($attempt, 'twk', 30);
        $attempt->forceFill(['expires_at' => now()->subSecond()])->save();

        $finished = $this->manager()->finish($attempt->refresh());

        $this->assertSame(AttemptStatus::Expired, $finished->status);
        $this->assertSame(150, $finished->twk_score);
        $this->assertSame(150, $finished->total_score);
    }

    public function test_the_read_path_closes_an_overdue_sitting_with_no_scheduler(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = ExamAttempt::factory()->forUserAndPackage($user, $package)->overdue()->create([
            'active_key' => $user->id.':'.$package->id,
            'current_question_id' => $package->questions()->first()->id,
            'last_activity_at' => now()->subMinutes(6),
        ]);
        $this->answerCorrectly($attempt, 'tiu', 35);

        $closed = $this->manager()->expireOverdue($user);

        $this->assertSame(1, $closed);
        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
        $this->assertSame(175, $attempt->tiu_score);
        $this->assertNull($attempt->active_key);
    }

    public function test_the_whole_table_sweep_closes_every_overdue_sitting_and_spares_the_live_ones(): void
    {
        $package = ExamPackage::factory()->complete()->create();

        $first = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->overdue()->create();
        $second = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->overdue()->create();
        $live = $this->manager()->startOrResume($this->user(), $package);

        $closed = $this->manager()->expireAllOverdue();

        $this->assertSame(2, $closed);
        $this->assertSame(AttemptStatus::Expired, $first->refresh()->status);
        $this->assertSame(AttemptStatus::Expired, $second->refresh()->status);
        $this->assertSame(AttemptStatus::InProgress, $live->refresh()->status);
    }

    public function test_an_open_sitting_is_left_alone_by_the_read_path(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->create();
        $attempt = $this->manager()->startOrResume($user, $package);

        $this->assertSame(0, $this->manager()->expireOverdue($user));
        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }
}
