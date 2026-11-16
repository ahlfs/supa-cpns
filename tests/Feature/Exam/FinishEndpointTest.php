<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

/**
 * The way out of the room.
 *
 * The blank guard is a courtesy the browser performs and the server re-checks,
 * because a disabled button is not a rule. The one paper the guard must NOT
 * refuse is the one whose clock ran out: a real CAT submits what is on the desk
 * at 00:00 and grades it as expired, so the forced submit the room performs when
 * time runs out has to land.
 */
final class FinishEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function sitting(): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();

        return [$user, app(AttemptManager::class)->startOrResume($user, $package)];
    }

    public function test_finishing_with_a_blank_item_is_refused_by_the_server(): void
    {
        [$user, $attempt] = $this->sitting();
        $this->answerEveryQuestion($attempt, 'best');

        $blank = $attempt->answers()->whereNotNull('selected_option_id')->first();
        $blank->forceFill(['selected_option_id' => null])->save();

        $this->actingAs($user)
            ->from('/tryout/attempt/'.$attempt->id)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect('/tryout/attempt/'.$attempt->id)
            ->assertSessionHasErrors('attempt');

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }

    public function test_finishing_a_full_paper_grades_it_and_leaves_the_room(): void
    {
        [$user, $attempt] = $this->sitting();
        $this->answerEveryQuestion($attempt, 'best');

        $this->actingAs($user)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect(route('hasil.show', $attempt));

        $graded = $attempt->refresh();

        $this->assertSame(AttemptStatus::Submitted, $graded->status);
        $this->assertSame(550, $graded->total_score);
    }

    /**
     * The forced submit. exam.js bypasses its own blank guard at 00:00 and posts
     * the paper as it stands, so an incomplete sitting whose clock has run out
     * must be graded as expired instead of bounced back for blanks — otherwise
     * the participant sits in a room that will never let them out.
     */
    public function test_a_sitting_that_ran_out_of_time_submits_whatever_is_on_the_paper(): void
    {
        [$user, $attempt] = $this->sitting();
        $this->answerCorrectly($attempt, 'twk', 30);
        $attempt->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->actingAs($user)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect(route('hasil.show', $attempt));

        $graded = $attempt->refresh();

        $this->assertSame(AttemptStatus::Expired, $graded->status);
        $this->assertSame(150, $graded->total_score);
        $this->assertSame(30, $graded->answers()->whereNotNull('selected_option_id')->count());
    }

    public function test_another_participants_attempt_cannot_be_finished(): void
    {
        [$user, $attempt] = $this->sitting();
        $this->answerEveryQuestion($attempt, 'best');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertForbidden();

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
        $this->assertSame(0, $attempt->subtopics()->count());
    }

    public function test_a_guest_cannot_finish_a_sitting(): void
    {
        [, $attempt] = $this->sitting();

        $this->post('/tryout/attempt/'.$attempt->id.'/selesai')->assertRedirect(route('login'));

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }

    public function test_finishing_twice_is_harmless(): void
    {
        [$user, $attempt] = $this->sitting();
        $this->answerEveryQuestion($attempt, 'best');

        $this->actingAs($user)->post('/tryout/attempt/'.$attempt->id.'/selesai');
        $this->actingAs($user)->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect(route('hasil.show', $attempt));

        $graded = $attempt->refresh();

        $this->assertSame(AttemptStatus::Submitted, $graded->status);
        $this->assertSame(550, $graded->total_score);
    }

    /**
     * The refusal has to be visible, not merely flashed: the room prints
     * `$errors->first()` above the item, and a participant bounced back with an
     * empty page would think their tap did nothing.
     *
     * Followed in one request chain on purpose. Reading the flash off the
     * redirect first (`assertSessionHasErrors`) calls `TestResponse::session()`,
     * which re-starts the already-saved store and ages the bag away before the
     * room is rendered — the assertion would then be measuring the harness, not
     * the app.
     */
    public function test_the_refusal_lands_in_the_room(): void
    {
        [$user, $attempt] = $this->sitting();

        $this->actingAs($user)
            ->from('/tryout/attempt/'.$attempt->id)
            ->followingRedirects()
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertOk()
            ->assertSee('Masih ada 110 soal yang belum dijawab.');
    }
}
