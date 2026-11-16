<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ExpireCommandTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    public function test_it_closes_every_overdue_attempt_without_touching_open_ones(): void
    {
        $package = ExamPackage::factory()->complete()->create();
        $abandoned = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->overdue()
            ->create();
        $running = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        $this->answerCorrectly($abandoned, 'twk', 30);

        $this->artisan('cpns:expire-attempts')->assertSuccessful();

        $this->assertSame(AttemptStatus::Expired, $abandoned->refresh()->status);
        $this->assertSame(150, $abandoned->total_score);
        $this->assertSame(AttemptStatus::InProgress, $running->refresh()->status);
    }

    public function test_it_says_so_when_there_is_nothing_to_do(): void
    {
        $this->artisan('cpns:expire-attempts')
            ->expectsOutput('Tidak ada percobaan yang perlu ditutup.')
            ->assertSuccessful();
    }
}
