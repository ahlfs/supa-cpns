<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the life of a sitting: it starts, resumes, restarts, finishes and closes
 * attempts. Time is never taken from the browser — the clock is `started_at` plus
 * the package duration, and expiry is evaluated on the read path, which is why
 * the application is correct with no scheduler running at all.
 */
final class AttemptManager
{
    public function __construct(
        private readonly ExamGrader $grader,
        private readonly AnswerRecorder $recorder,
    ) {}

    /**
     * Starting twice is resuming: someone who closed the browser on item 40 comes
     * back to item 40 with the clock still running, never to a fresh paper.
     */
    public function startOrResume(User $user, ExamPackage $package): ExamAttempt
    {
        $this->expireOverdue($user);

        $existing = $user->attempts()
            ->where('exam_package_id', $package->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $startedAt = now();

        return ExamAttempt::create([
            'user_id' => $user->id,
            'exam_package_id' => $package->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes($package->duration_minutes),
            'last_activity_at' => $startedAt,
            'active_key' => $user->id.':'.$package->id,
        ]);
    }

    /**
     * The explicit "start over" action. The abandoned sitting is closed and graded
     * as expired rather than deleted: it happened, and its scores are its own.
     */
    public function restart(User $user, ExamPackage $package): ExamAttempt
    {
        $open = $user->attempts()
            ->where('exam_package_id', $package->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('id')
            ->first();

        if ($open !== null) {
            $this->recorder->closeOpenInterval($open);
            $this->grader->grade($open, expired: true);
        }

        return $this->startOrResume($user, $package);
    }

    /**
     * Finishing is refused while anything is still unanswered — in the service,
     * not only by disabling a button. A sitting that ran out of time is graded
     * without that check, because there is nobody left to answer.
     */
    public function finish(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        if ($attempt->isOverdue()) {
            return $this->grader->grade($attempt, expired: true);
        }

        $total = $attempt->totalItems();
        $answered = $attempt->answeredCount();

        if ($answered < $total) {
            throw ValidationException::withMessages([
                'attempt' => 'Masih ada '.($total - $answered).' soal yang belum dijawab.',
            ]);
        }

        $this->recorder->closeOpenInterval($attempt);

        return $this->grader->grade($attempt);
    }

    /** Closes and grades whatever this participant left behind. */
    public function expireOverdue(User $user): int
    {
        $overdue = $user->attempts()
            ->where('status', AttemptStatus::InProgress)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $attempt) {
            $this->grader->grade($attempt, expired: true);
        }

        return $overdue->count();
    }

    /** The whole-table version, used by the optional scheduled sweep. */
    public function expireAllOverdue(): int
    {
        $overdue = ExamAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('expires_at', '<=', now())
            ->get();

        DB::transaction(function () use ($overdue): void {
            foreach ($overdue as $attempt) {
                $this->grader->grade($attempt, expired: true);
            }
        });

        return $overdue->count();
    }
}
