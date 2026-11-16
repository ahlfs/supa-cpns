<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two writes the room makes on every tap: one answer and one position.
 *
 * The browser is optimistic — it recolours the option before the request is
 * answered — so every one of these tests is really about whether the server
 * agrees. The same body can arrive twice (a fast tap on a slow line), a null
 * option is a skip rather than a malformed request, and a late write must not
 * slip into a sitting that has already been graded.
 */
final class AnswerEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: ExamAttempt, 2: Question}
     */
    private function sitting(): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        return [$user, $attempt, $package->questions()->with('options')->first()];
    }

    public function test_choosing_an_option_saves_it_and_returns_the_clock(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();

        $response = $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $option->id],
        );

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answered', 1)
            ->assertJsonPath('blank', 109);

        // The clock is the server's; a second may have passed since the sitting started.
        $this->assertGreaterThan(5900, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(6000, $response->json('remaining_seconds'));

        $this->assertSame($option->id, $attempt->refresh()->selectedOptionFor($question));
    }

    public function test_sending_the_same_answer_twice_leaves_one_row(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();
        $url = '/tryout/attempt/'.$attempt->id.'/jawaban';

        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);
        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
    }

    public function test_skipping_travels_as_a_null_option(): void
    {
        [$user, $attempt, $question] = $this->sitting();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => null],
        )->assertOk()->assertJsonPath('answered', 0);

        $this->assertNull($attempt->refresh()->selectedOptionFor($question));
    }

    public function test_flagging_an_item_does_not_erase_its_answer(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();
        $url = '/tryout/attempt/'.$attempt->id.'/jawaban';

        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);
        $this->actingAs($user)->putJson($url, [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
            'is_flagged' => true,
        ]);
        $this->actingAs($user)->putJson($url, [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $answer = $attempt->refresh()->answers()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($answer->is_flagged);
        $this->assertSame($option->id, $answer->selected_option_id);
    }

    public function test_moving_on_records_where_the_participant_is(): void
    {
        [$user, $attempt, $question] = $this->sitting();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/posisi',
            ['question_id' => $question->id],
        )->assertOk();

        $this->assertSame($question->id, $attempt->refresh()->current_question_id);
    }

    public function test_a_question_outside_the_package_is_refused(): void
    {
        [$user, $attempt] = $this->sitting();
        $stranger = Question::factory()->twk()->create();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $stranger->id, 'selected_option_id' => $stranger->options->first()->id],
        )->assertStatus(422);
    }

    public function test_a_late_answer_is_refused_and_the_sitting_is_closed(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertStatus(422);

        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
    }

    /**
     * A write that is refused because the sitting is over has to say *which*
     * sitting. The room reads `attempt` off the error body and reloads, because
     * there is nothing left for the participant to do on that page: the server
     * has already graded it. Without that key the room would sit at 00:00 with
     * every tap failing forever.
     */
    public function test_a_late_answer_says_which_sitting_has_ended(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertStatus(422)
            ->assertJsonPath('attempt', $attempt->id);
    }

    /**
     * The other half of the same hand-off. A participant with the room open in two
     * tabs submits in one of them; the write still in flight in the other must not
     * land on a graded paper, and that tab has to be told to leave rather than left
     * tapping at a sitting that is closed. This is the `! isOpen()` branch, which
     * the expiry test above does not reach.
     */
    public function test_a_late_answer_to_a_graded_sitting_also_names_the_attempt(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $attempt->forceFill(['status' => AttemptStatus::Submitted])->save();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertStatus(422)
            ->assertJsonPath('attempt', $attempt->id);

        $this->assertSame(AttemptStatus::Submitted, $attempt->refresh()->status);
        $this->assertSame(0, $attempt->answers()->count());
    }

    public function test_another_participants_attempt_is_forbidden(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertForbidden();

        $this->assertSame(0, $attempt->refresh()->answers()->count());
    }

    public function test_another_participants_attempt_cannot_be_moved(): void
    {
        [$owner, $attempt, $question] = $this->sitting();
        $other = $attempt->package->questions()->where('questions.id', '!=', $question->id)->firstOrFail();

        $this->actingAs($owner)->putJson(
            '/tryout/attempt/'.$attempt->id.'/posisi',
            ['question_id' => $question->id],
        )->assertOk();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson(
            '/tryout/attempt/'.$attempt->id.'/posisi',
            ['question_id' => $other->id],
        )->assertForbidden();

        // The owner's place is where they left it: a refused move must not be a
        // move, or a stranger could quietly re-point someone's timing interval.
        $this->assertSame($question->id, $attempt->refresh()->current_question_id);
    }

    public function test_a_guest_cannot_write_to_a_sitting(): void
    {
        [, $attempt, $question] = $this->sitting();

        $this->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertUnauthorized();
    }

    /**
     * Task 9 shipped the room with `data-answer-url=""` and the same for the
     * position URL, behind a `Route::has()` hand-off so the markup could exist
     * before the handlers did. These routes are what fill them in, and the fill is
     * invisible in the Blade: a route renamed or removed leaves the attribute
     * empty again and the room's taps never leave the browser.
     */
    public function test_the_room_is_handed_the_real_write_urls(): void
    {
        [$user, $attempt] = $this->sitting();

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();

        $this->assertStringContainsString('data-answer-url="'.route('tryout.answer', $attempt).'"', $html);
        $this->assertStringContainsString('data-position-url="'.route('tryout.position', $attempt).'"', $html);
        $this->assertStringContainsString('action="'.route('tryout.finish', $attempt).'"', $html);
    }

    /**
     * The forced-password-change gate answered a 302 unconditionally, while these
     * endpoints return JSON and `resources/js/modules/exam.js` reads
     * `error.data.message` off the body. So an administrator resetting a
     * participant's password mid-sitting — the design's own recovery path — handed
     * every answer save a redirect the room could not read: nothing was saved, the
     * message line stayed empty, and the participant could neither finish nor save
     * before the clock ran out.
     *
     * A request that expects JSON now gets a machine-readable refusal; the page a
     * browser navigates to still redirects, which is where the participant fixes
     * the password before coming back to the still-running sitting.
     */
    public function test_a_forced_password_change_refuses_a_json_write_without_a_redirect(): void
    {
        [$user, $attempt, $question] = $this->sitting();

        // What an admin reset does mid-sitting (UserController::resetPassword).
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user->refresh())->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        );

        $response->assertStatus(409);

        $message = $response->json('message');

        $this->assertIsString($message);
        $this->assertStringContainsString('password', mb_strtolower($message), 'The refusal has to name what to do.');

        // Nothing was written, and the sitting is still open for the next attempt.
        $this->assertSame(0, $attempt->refresh()->answers()->count());
        $this->assertTrue($attempt->status->isOpen());

        // The position save the navigator makes is refused the same way, rather
        // than moving an interval nobody asked to move.
        $this->actingAs($user->refresh())->putJson(
            '/tryout/attempt/'.$attempt->id.'/posisi',
            ['question_id' => $question->id],
        )->assertStatus(409);

        // And the browser-shaped request still redirects to the password form.
        $this->actingAs($user->refresh())
            ->get('/tryout/attempt/'.$attempt->id)
            ->assertRedirect(route('password.edit'));
    }
}
