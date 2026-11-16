<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_casts_to_an_enum_and_defaults_to_peserta(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Peserta, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_the_admin_state_passes_every_gate_and_a_peserta_passes_none(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('anything-at-all'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('anything-at-all'));
    }

    public function test_a_participant_number_is_derived_from_the_year_and_the_id(): void
    {
        $user = User::factory()->create();
        $user->assignParticipantNumber();

        $this->assertSame(
            sprintf('%s-%06d', now()->format('Y'), $user->id),
            $user->fresh()->participant_number,
        );
    }

    public function test_the_blueprint_numbers_come_from_config_and_not_from_code(): void
    {
        $this->assertSame(30, QuestionCategory::Twk->itemCount());
        $this->assertSame(35, QuestionCategory::Tiu->itemCount());
        $this->assertSame(45, QuestionCategory::Tkp->itemCount());
        $this->assertSame(150, QuestionCategory::Twk->maxScore());
        $this->assertSame(175, QuestionCategory::Tiu->maxScore());
        $this->assertSame(225, QuestionCategory::Tkp->maxScore());
        $this->assertSame(65, QuestionCategory::Twk->threshold());
        $this->assertSame(80, QuestionCategory::Tiu->threshold());
        $this->assertSame(166, QuestionCategory::Tkp->threshold());
        $this->assertSame(110, config('cpns.total_items'));
        $this->assertSame(550, config('cpns.total_max'));
        $this->assertSame(100, config('cpns.duration_minutes'));

        $this->assertTrue(QuestionCategory::Twk->requiresKey());
        $this->assertTrue(QuestionCategory::Tiu->requiresKey());
        $this->assertFalse(QuestionCategory::Tkp->requiresKey());

        $this->assertTrue(AttemptStatus::InProgress->isOpen());
        $this->assertFalse(AttemptStatus::Submitted->isOpen());
        $this->assertFalse(AttemptStatus::Expired->isOpen());
    }
}
