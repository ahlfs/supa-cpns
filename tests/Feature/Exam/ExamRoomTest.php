<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AnswerRecorder;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

/**
 * The participant's heart of the module: the package list and the room.
 *
 * The room's assertions are mostly about the DOM contract the browser module
 * reads, because that contract is what Task 10's endpoints and the navigator
 * are wired to — a renamed hook is a silent breakage, not a visible one.
 */
final class ExamRoomTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private function published(): ExamPackage
    {
        return ExamPackage::factory()->complete()->published()->create();
    }

    /** A participant sitting a fresh, complete paper. */
    private function sit(User $user): ExamAttempt
    {
        return app(AttemptManager::class)->startOrResume($user, $this->published());
    }

    /**
     * The finish <button> tags on both faces: the desktop strip's and the one
     * inside the navigator. `str_contains($tag, 'disabled')` would be a false
     * positive, because the sheet's copy carries the `disabled:opacity-50`
     * utility in its class list.
     *
     * @return list<string>
     */
    private function finishButtons(string $html): array
    {
        preg_match_all('/<button[^>]*data-exam-finish-button[^>]*>/', $html, $matches);

        return $matches[0];
    }

    /** @param list<string> $tags */
    private function disarmed(array $tags): int
    {
        return count(array_filter(
            $tags,
            static fn (string $tag): bool => preg_match('/ disabled(?=[ >])/', $tag) === 1,
        ));
    }

    /**
     * The live blank counts a face shows beside its Finish button, in document
     * order: the desktop strip's and the navigator's. The module keeps every
     * `[data-exam-blank]` node up to date, so a face without one has no way of
     * saying why its button is closed.
     *
     * @return list<int>
     */
    private function blankCount(string $html): array
    {
        preg_match_all('/data-exam-blank[^>]*>(\d+)</', $html, $matches);

        return array_map(intval(...), $matches[1]);
    }

    public function test_a_guest_is_kept_out(): void
    {
        $this->get('/tryout')->assertRedirect(route('login'));
    }

    public function test_starting_sends_the_participant_into_the_room(): void
    {
        $user = User::factory()->create();
        $package = $this->published();

        $response = $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai');

        $attempt = ExamAttempt::query()->firstOrFail();

        $response->assertRedirect(route('tryout.room', $attempt));
        $this->assertSame($user->id, $attempt->user_id);
    }

    public function test_the_room_carries_the_contract_the_script_needs(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('data-exam', escape: false);
        $this->assertMatchesRegularExpression('/data-remaining="(59\d\d|6000)"/', $response->getContent());
        $response->assertSee('data-total="110"', escape: false);
        $response->assertSee('data-current-question-id="'.$package->questions()->first()->id.'"', escape: false);
        $response->assertSee('name="csrf-token"', escape: false);

        // Every item is rendered; exactly one of them is visible. The navigator
        // cell carries a `data-exam-cell-flag` child, so a plain substr_count of
        // "data-exam-cell" counts 220 — the lookahead pins the cells themselves.
        $html = $response->getContent();
        $this->assertSame(110, substr_count($html, 'data-exam-item="'));
        $this->assertSame(110, preg_match_all('/data-exam-cell(?![\w-])/', $html));
        $this->assertSame(1, substr_count($html, 'data-exam-item="'.$package->questions()->first()->id.'"'));

        // The rest of the hooks the module and Task 10's endpoints reach for.
        foreach ([
            'data-answer-url',
            'data-position-url',
            'data-answered="0"',
            'data-exam-option',
            'data-option-id="',
            'data-label="A"',
            'data-status="blank"',
            'data-question-id="',
            'data-exam-number',
            'data-exam-clock',
            'data-exam-status',
            'data-exam-sheet',
            'data-exam-sheet-toggle',
            'data-exam-finish',
        ] as $hook) {
            $response->assertSee($hook, escape: false);
        }
    }

    public function test_the_room_does_not_render_the_marketing_chrome(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id);

        $response->assertDontSee('data-section="header"', escape: false);
        $response->assertDontSee('data-section="footer"', escape: false);
    }

    public function test_another_participants_sitting_is_a_forbidden_page(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($owner, $package);

        $this->actingAs($stranger)->get('/tryout/attempt/'.$attempt->id)->assertForbidden();
    }

    public function test_an_unpublished_package_cannot_be_started(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->create();

        $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai')->assertNotFound();
        $this->assertSame(0, ExamAttempt::query()->count());
    }

    public function test_the_package_list_offers_a_resume_for_an_open_sitting(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout');

        $response->assertOk();
        $response->assertSee('Lanjutkan');
        $response->assertSee('Mulai ulang');
    }

    /**
     * The dashboard contradicted itself: DashboardController passed a null open
     * attempt for every package card, so a package with a sitting still running
     * read "Sedang dikerjakan" in the block above and offered "Mulai tryout" in
     * the list below — two statements about the same package on one page. The card
     * now reads the same open-attempt map the sibling list (the Exam package
     * controller's index) builds.
     */
    public function test_the_dashboard_card_agrees_with_the_running_sitting_above_it(): void
    {
        $user = User::factory()->create();
        $running = $this->published();
        $untouched = $this->published();

        $attempt = app(AttemptManager::class)->startOrResume($user, $running);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas(
            'openAttempts',
            fn ($map): bool => $map->get($running->id)?->id === $attempt->id
                && $map->has($untouched->id) === false,
        );

        $html = (string) $response->getContent();

        // Exactly one card still offers a fresh start: the package nobody began.
        $this->assertSame(1, substr_count($html, 'Mulai tryout'), 'The running package must not still offer "Mulai tryout".');

        // The running package is named twice — once in the block above and once in
        // its own card — and both of them offer to resume it.
        $this->assertStringContainsString('Sedang dikerjakan', $html);
        $this->assertSame(2, substr_count($html, 'Lanjutkan'));
    }

    /**
     * The discovery list, both directions: a published package's title is what a
     * participant has to be able to see, and a draft's must not be. Nothing
     * asserted either, so deleting the published filter kept the suite green while
     * participants saw packages nobody had released.
     */
    public function test_the_tryout_list_shows_published_packages_and_hides_the_rest(): void
    {
        $user = User::factory()->create();
        $published = ExamPackage::factory()->complete()->published()->create(['title' => 'Tryout Sudah Terbit']);
        $draft = ExamPackage::factory()->complete()->create(['title' => 'Tryout Masih Draf']);

        $response = $this->actingAs($user)->get('/tryout');

        $response->assertOk();
        $response->assertSee('Tryout Sudah Terbit');
        $response->assertDontSee('Tryout Masih Draf');

        // And the view's own list is the published one, not merely a page that
        // happens to render one card.
        $response->assertViewHas('packages', fn ($packages): bool => $packages->pluck('id')->all() === [$published->id]);
    }

    public function test_restarting_closes_the_old_sitting_and_opens_a_fresh_one(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $old = app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai-ulang');

        $this->assertSame(AttemptStatus::Expired, $old->refresh()->status);
        $this->assertSame(2, ExamAttempt::query()->count());

        $fresh = ExamAttempt::query()->latest('id')->firstOrFail();

        // The restart lands in the new room, so the room is where it has to say
        // what happened to the sitting left behind.
        $response->assertRedirect(route('tryout.room', $fresh));

        $this->actingAs($user)
            ->get('/tryout/attempt/'.$fresh->id)
            ->assertOk()
            ->assertSee('ditutup dan dinilai sebagai waktu habis');
    }

    public function test_a_finished_sitting_leaves_the_room(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $attempt->forceFill(['status' => AttemptStatus::Submitted, 'submitted_at' => now()])->save();

        $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->assertRedirect(route('hasil.show', $attempt));
    }

    public function test_the_room_shows_a_saved_answer_and_its_review_mark(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $question = $package->questions()->with('options')->first();
        $option = $question->options->first();

        app(AnswerRecorder::class)->record($attempt, $question->id, $option->id, true);

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();

        // The navigator cell for that item is green and carries the review dot.
        $this->assertMatchesRegularExpression(
            '/data-exam-cell(?![\w-])(?=[^>]*data-question-id="'.$question->id.'")'
            .'(?=[^>]*data-status="answered")(?=[^>]*data-flag="true")/',
            $html,
        );

        // The item on screen comes back with the chosen option and the flag on.
        $this->assertMatchesRegularExpression(
            '/data-exam-option(?=[^>]*data-option-id="'.$option->id.'")(?=[^>]*data-selected="true")/',
            $html,
        );
        $this->assertMatchesRegularExpression('/data-exam-flag(?=[^>]*data-flagged="true")/', $html);

        // Counted once, on the desktop strip.
        $this->assertMatchesRegularExpression('/data-exam-answered(?=[^>]*>\s*1\s*<)/', $html);
    }

    public function test_finish_waits_for_the_last_item_and_lives_inside_the_sheet(): void
    {
        $user = User::factory()->create();
        $attempt = $this->sit($user);

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();

        // Anchor the slice on the <aside> element. The string `data-exam-sheet`
        // matches the phone bar's `data-exam-sheet-toggle` first, and that button
        // appears *before* the panel — slicing from it swallows the desktop strip's
        // finish form too, so the assertions below would pass with the sheet's own
        // form deleted. `data-exam-answered` lives only in the strip: it is the
        // tell that this slice stayed out of it.
        $open = strpos($html, '<aside');
        $close = $open === false ? false : strpos($html, '</aside>', $open);

        $this->assertIsInt($open, 'The navigator panel is an <aside>.');
        $this->assertIsInt($close, 'The navigator panel is closed by </aside>.');

        $sheet = substr($html, (int) $open, (int) $close - (int) $open);

        // On a phone the only way out is behind the sheet toggle, so nobody ends
        // the exam at item 60 while reaching for "next": the panel carries exactly
        // one finish form, and it is the panel's own.
        $this->assertStringContainsString('data-exam-finish', $sheet);
        $this->assertSame(1, substr_count($sheet, 'data-exam-finish-button'));
        $this->assertStringNotContainsString('data-exam-answered', $sheet);

        // The module's open toggle is `data-open`; the class that turns that
        // attribute into the slide has to be on the panel, or the sheet never
        // comes up on a phone.
        $this->assertStringContainsString('data-[open=true]:translate-y-0', $sheet);

        // One policy on both faces, not a desktop-only guard plus an always-live
        // phone button: while items are blank BOTH copies are closed, and the
        // count that closes them is live beside each one — which is what makes the
        // promise printed under the phone's button true.
        $this->assertCount(2, $buttons = $this->finishButtons($html));
        $this->assertSame(2, $this->disarmed($buttons), 'Both copies disarm while items are blank.');
        $this->assertSame([110], $this->blankCount($sheet), 'The phone face counts its blanks beside the button.');
        $this->assertSame([110, 110], $this->blankCount($html), 'Both faces carry the same live blank count.');

        // Part-way through: still closed on both faces, and the count moves.
        $this->answerCorrectly($attempt, 'twk', 1);

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();

        $this->assertCount(2, $buttons = $this->finishButtons($html));
        $this->assertSame(2, $this->disarmed($buttons), 'One answered item does not open either copy.');
        $this->assertSame([109, 109], $this->blankCount($html));

        $this->answerEveryQuestion($attempt);

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();

        $this->assertCount(2, $buttons = $this->finishButtons($html));
        $this->assertSame(0, $this->disarmed($buttons), 'Both copies arm once every item is answered.');
        $this->assertSame([0, 0], $this->blankCount($html));
    }

    public function test_the_room_shows_the_servers_own_refusal_of_an_early_finish(): void
    {
        $user = User::factory()->create();
        $attempt = $this->sit($user);

        // What a refused finish looks like on the next read: the controller
        // flashes the manager's "Masih ada N soal yang belum dijawab." and the
        // room renders it. (Task 10 is what posts that refusal.)
        //
        // The session is JSON-serialized, so the bag is seeded in its stored
        // shape — a ViewErrorBag object here would be marshalled into an empty
        // bag on the way in (`Store::marshalErrorBag()`).
        $response = $this->actingAs($user)
            ->withSession(['errors' => [
                'default' => [
                    'format' => ':message',
                    'messages' => ['Masih ada 60 soal yang belum dijawab.'],
                ],
            ]])
            ->get('/tryout/attempt/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('Masih ada 60 soal yang belum dijawab.');
    }

    /**
     * The shared header must offer the application's own routes — and it has to
     * know who is reading it. A guest gets the marketing CTAs; someone who is
     * already signed in gets their dashboard instead of an invitation to register
     * for the account they already hold.
     *
     * The assertions are on `data-header-account` rather than on the destination
     * URLs, because the mega menu carries the login and register links for guests
     * on every page and would make a URL assertion pass for the wrong reason.
     */
    public function test_the_shared_header_offers_the_apps_own_routes(): void
    {
        $guest = $this->get('/')->getContent();

        $this->assertStringContainsString(route('login', absolute: false), $guest);
        $this->assertStringContainsString(route('register', absolute: false), $guest);
        $this->assertStringNotContainsString('/apps/masuk', $guest);
        $this->assertStringNotContainsString('/apps/daftar', $guest);
        $this->assertStringNotContainsString('data-header-account="dashboard"', $guest);

        // The dashboard wears the marketing chrome, so the switch has to happen
        // there too — and a plain participant is offered no panel link.
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();

        $this->assertStringContainsString('data-header-account="dashboard"', $html);
        $this->assertStringNotContainsString('data-header-account="admin"', $html);
        $this->assertStringNotContainsString('/apps/masuk', $html);
        $this->assertStringNotContainsString('/apps/daftar', $html);
    }

    /**
     * `tools/exam-smoke.mjs` drives a real sitting, and a selector it reaches for
     * that the markup no longer renders fails at the operator's terminal instead of
     * in a test. Every data hook it names is asserted here against the room that
     * has to carry it — and it is why the script navigates with the NEXT control:
     * the skip control clears the item it is invoked on, so a script that skipped
     * and then asserted the answer had survived the reload was reading a cell it
     * had emptied itself.
     */
    public function test_the_smoke_script_reaches_for_hooks_the_room_renders(): void
    {
        $user = User::factory()->create();
        $attempt = $this->sit($user);

        $html = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->getContent();
        $script = (string) file_get_contents(base_path('tools/exam-smoke.mjs'));

        preg_match_all('/\[(data-exam[-\w]*)/', $script, $matches);

        $hooks = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($hooks, 'The smoke script must name the room\'s hooks.');

        foreach ($hooks as $hook) {
            $this->assertStringContainsString($hook, $html, "The room must render `{$hook}`, which tools/exam-smoke.mjs reads.");
        }

        // And it moves between items with the control that does not clear the item
        // it is on.
        $this->assertStringContainsString('[data-exam-next]', $script);
    }
}
