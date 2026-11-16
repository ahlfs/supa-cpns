<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * The sitting clock comes from the attempt's own package: the
     * `exam_packages.duration_minutes` column is the single source of truth for
     * how long a sitting lasts. `config('cpns.duration_minutes')` is only the
     * default a *new* package is created with (see `ExamPackageFactory`), so it
     * must never be read here — a package whose duration differs from the config
     * default would otherwise be handed a clock that disagrees with itself.
     *
     * Derived here rather than in `definition()` because only at this point has
     * the nested `ExamPackage::factory()` been resolved into a real package id.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ExamAttempt $attempt): void {
            // A state (see overdue()) or an explicit attribute may pin the expiry.
            if ($attempt->expires_at !== null) {
                return;
            }

            $attempt->expires_at = $attempt->started_at
                ->copy()
                ->addMinutes($this->packageDurationMinutes((int) $attempt->exam_package_id));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now();

        return [
            'user_id' => User::factory(),
            'exam_package_id' => ExamPackage::factory(),
            'status' => AttemptStatus::InProgress->value,
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt,
            'active_key' => null,
        ];
    }

    /**
     * The length of the sitting, read from the package, never from config.
     */
    private function packageDurationMinutes(int $examPackageId): int
    {
        return (int) ExamPackage::query()->whereKey($examPackageId)->value('duration_minutes');
    }

    /**
     * An open attempt that is already past its expiry: what the read path must
     * close and grade without any scheduler running. Both timestamps are pinned
     * explicitly, so the expiry does not depend on the package duration — an
     * expired sitting is expired whatever its package says.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => now()->subMinutes((int) config('cpns.duration_minutes') + 5),
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    /**
     * A finished, graded sitting — the only shape this application ever writes.
     *
     * `ExamGrader::grade()` sets `status` and all four score columns in one
     * transaction, so a `submitted` row with null scores is a state the app cannot
     * produce: `scoreFor()` would coerce the nulls through an int cast, the paper
     * would report 0 per subtest, and `hasPassed()` would call a finished paper
     * "gagal" with zero scores on it.
     *
     * The default board is a passing one (all three subtests above their
     * thresholds) so a fixture named "submitted" cannot silently mean "failed".
     * A test that wants a particular verdict says so with `withScores()`, which
     * recomputes the total — something a bare `['total_score' => 200]` cannot do
     * without leaving the four columns disagreeing with each other.
     */
    public function submitted(): static
    {
        return $this->withScores(100, 100, 170)->state(fn (array $attributes): array => [
            'status' => AttemptStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    /**
     * The four score columns the way the grader writes them: one per subtest, and
     * a total that is their sum rather than a fifth number to keep in step.
     */
    public function withScores(int $twk, int $tiu, int $tkp): static
    {
        return $this->state(fn (array $attributes): array => [
            'twk_score' => $twk,
            'tiu_score' => $tiu,
            'tkp_score' => $tkp,
            'total_score' => $twk + $tiu + $tkp,
        ]);
    }

    public function forUserAndPackage(User $user, ExamPackage $package): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'exam_package_id' => $package->id,
        ]);
    }
}
