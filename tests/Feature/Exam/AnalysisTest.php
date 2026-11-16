<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Models\AttemptAnalysis;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Ai\AnalysisFailed;
use App\Services\Ai\AttemptAnalyzer;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use App\Services\Exam\ScoreReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

/**
 * The AI analysis of a finished sitting: the panel, the route, the gateway call
 * and the one stored row.
 *
 * Two things are asserted more than once because they are the whole reason this
 * feature is safe to ship. The gateway is called once per sitting — a reload of
 * the result page, or a second tap, must not spend money again (it is called once
 * today only because the row is read back instead). And the text a machine wrote
 * is escaped: it is the only string in this application that was not written by a
 * person, so `<script>` in it has to come out as `<script>`.
 */
final class AnalysisTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private const BASE_URL = 'http://127.0.0.1:9898/v1';

    private const KEY = 'lam-router-key';

    private const MODEL = 'deepseek-v3.2';

    /** The gateway settings, as the real .env carries them. No request leaves here. */
    private function configureGateway(string $key = self::KEY, string $model = self::MODEL): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.base_url', self::BASE_URL);
        config()->set('ai.api_key', $key);
        config()->set('ai.model', $model);
        config()->set('ai.timeout', 30);
        config()->set('ai.max_tokens', 900);
        config()->set('ai.temperature', 0.3);
    }

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function gradedPaper(string $strategy = 'best'): array
    {
        $user = User::factory()->create();

        return [$user, $this->sitFor($user, $strategy)];
    }

    /** A second sitting of the same participant, on a package of its own. */
    private function sitFor(User $user, string $strategy = 'best'): ExamAttempt
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, $strategy);

        return app(ExamGrader::class)->grade($attempt);
    }

    /**
     * A chat-completions body in the shape the gateway answers with.
     *
     * @return array<string, mixed>
     */
    private function gatewayAnswer(string $content, string $model = 'deepseek-v4.1-flash'): array
    {
        return [
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'model' => $model,
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 812, 'completion_tokens' => 240],
        ];
    }

    private function fakeAnswer(string $content): void
    {
        Http::fake(['127.0.0.1:9898/*' => Http::response($this->gatewayAnswer($content))]);
    }

    /** The submit button is the panel's whole affordance, so it is what is looked for. */
    private function assertPanelAbsent(string $html): void
    {
        $this->assertStringNotContainsString('Buat analisa AI', $html, 'The panel must not be rendered at all.');
        $this->assertStringNotContainsString('data-analysis', $html, 'No part of the panel may reach the page.');
    }

    public function test_the_panel_is_absent_when_the_feature_is_disabled(): void
    {
        $this->configureGateway();
        config()->set('ai.enabled', false);

        [$user, $attempt] = $this->gradedPaper();

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $this->assertPanelAbsent((string) $response->getContent());
        // The rest of the page is untouched: this is absence, not a broken layout.
        $response->assertSee('Skor total');
    }

    public function test_the_panel_is_absent_when_no_gateway_key_is_configured(): void
    {
        $this->configureGateway();
        config()->set('ai.api_key', null);

        [$user, $attempt] = $this->gradedPaper();

        $this->assertPanelAbsent(
            (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent(),
        );
    }

    /**
     * A checkout whose code arrived before `migrate` ran, which is what the live
     * host is between a deploy and its migration. The panel must vanish rather
     * than take the results page down with it — the participant came here for a
     * score, not for this feature.
     */
    public function test_the_panel_is_absent_when_the_table_does_not_exist_yet(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();

        Schema::drop('attempt_analyses');

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $this->assertPanelAbsent((string) $response->getContent());
        $response->assertSee('Skor total');
    }

    public function test_a_guest_cannot_ask_for_an_analysis(): void
    {
        $this->configureGateway();
        Http::fake();
        [, $attempt] = $this->gradedPaper();

        $this->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect(route('login'));

        $this->assertDatabaseCount('attempt_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_another_participants_sitting_cannot_be_analysed(): void
    {
        $this->configureGateway();
        Http::fake();
        [, $attempt] = $this->gradedPaper();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertForbidden();

        // The JSON face of the same refusal, because the panel is not the only
        // client this route may ever have.
        $this->actingAs($stranger)
            ->postJson('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertForbidden();

        $this->assertDatabaseCount('attempt_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_an_unsubmitted_sitting_is_sent_back_to_the_room(): void
    {
        $this->configureGateway();
        Http::fake();

        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerCorrectly($attempt, 'twk', 5);

        $this->actingAs($user)
            ->post('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertRedirect(route('tryout.room', $attempt));

        $this->actingAs($user)
            ->postJson('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('attempt', $attempt->id);

        $this->assertDatabaseCount('attempt_analyses', 0);
        Http::assertNothingSent();
    }

    /**
     * The route is not the only door: nothing may analyse a sitting whose score is
     * not frozen yet, however it is called.
     */
    public function test_the_analyser_refuses_an_open_sitting_even_when_called_directly(): void
    {
        $this->configureGateway();
        Http::fake();

        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        try {
            app(AttemptAnalyzer::class)->analyze($attempt);
            $this->fail('An open sitting must not be analysed.');
        } catch (AnalysisFailed $failure) {
            $this->assertStringContainsString('belum selesai', $failure->getMessage());
        }

        $this->assertDatabaseCount('attempt_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_a_generation_stores_exactly_one_row_and_the_page_shows_it(): void
    {
        $this->configureGateway();
        $this->fakeAnswer("Skor totalmu kuat di TWK.\n\nRencana belajar: kerjakan 20 soal TIU deret angka setiap hari.");

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->post('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertRedirect(route('hasil.show', $attempt));

        $this->assertDatabaseCount('attempt_analyses', 1);

        $stored = AttemptAnalysis::query()->firstOrFail();

        $this->assertSame($attempt->id, $stored->attempt_id);
        $this->assertSame(
            "Skor totalmu kuat di TWK.\n\nRencana belajar: kerjakan 20 soal TIU deret angka setiap hari.",
            $stored->content,
        );
        // The gateway names the model that actually answered, and that is the name
        // worth storing — the configured one is only a fallback.
        $this->assertSame('deepseek-v4.1-flash', $stored->model);
        $this->assertSame(812, $stored->prompt_tokens);
        $this->assertSame(240, $stored->completion_tokens);

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Skor totalmu kuat di TWK.')
            ->assertSee('Rencana belajar: kerjakan 20 soal TIU deret angka setiap hari.');
    }

    /**
     * The payload is the feature. If the model is not told the participant's real
     * numbers it can only guess at them, and the system prompt's ban on inventing
     * scores would leave it with nothing to say.
     */
    public function test_the_request_carries_the_gateway_settings_and_the_participants_own_numbers(): void
    {
        $this->configureGateway(model: 'deepseek-v3.2');
        $this->fakeAnswer('Oke, ini analisanya.');

        // A paper that fell short everywhere, so the subtest lines carry real
        // shortfalls and a real guess count instead of zeroes.
        [$user, $attempt] = $this->gradedPaper('worst');

        $report = new ScoreReport($attempt);
        $timing = $report->timing();
        $rows = $report->subtopics();
        $weakest = $rows->first();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect();

        /** @var array<string, mixed> $payload */
        $payload = [];

        Http::assertSent(function (Request $request) use (&$payload): bool {
            $payload = $request->data();

            return true;
        });

        // Where it went, and as whom.
        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE_URL.'/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY));

        $this->assertSame(self::MODEL, $payload['model'] ?? null, 'The configured model must travel in the body.');
        $this->assertSame(900, (int) ($payload['max_tokens'] ?? 0));

        $messages = $payload['messages'] ?? [];

        $this->assertCount(2, $messages, 'One system message and one user message, in that order.');
        $this->assertSame('system', $messages[0]['role'] ?? null);
        $this->assertSame('user', $messages[1]['role'] ?? null);

        $system = (string) ($messages[0]['content'] ?? '');
        $facts = (string) ($messages[1]['content'] ?? '');

        $this->assertStringContainsString('bahasa Indonesia', $system, 'The system message must fix the language.');
        $this->assertStringContainsString('"kamu"', $system, 'The system message must fix how the participant is addressed.');
        $this->assertStringContainsString('Jangan mengarang', $system, 'The system message must forbid invented numbers.');
        $this->assertStringContainsString('rencana belajar', $system, 'The system message must ask for the study plan.');

        // The participant's own numbers, read back out of the report rather than
        // retyped here.
        $this->assertStringContainsString('Skor total: '.$report->total().' dari '.$report->maximum(), $facts);
        $this->assertStringContainsString('belum lolos ambang batas', $facts);
        $this->assertStringContainsString('SUBTOPIK (terlemah lebih dulu', $facts);
        $this->assertStringContainsString(
            $weakest['label'].' ('.$weakest['category'].')',
            $facts,
            'The weakest subtopic must be named.',
        );
        $this->assertStringContainsString('skor '.$weakest['score'].' dari '.$weakest['available'], $facts);
        $this->assertStringContainsString(
            'Dijawab kurang dari '.$timing['guess_threshold'].' detik lalu salah: '.$timing['guesses'].' soal',
            $facts,
            'The guess count must be the one the report computed.',
        );
        $this->assertStringContainsString('adalah perkiraan', $facts, 'The per-item timing must be labelled an estimate.');

        // Every row, weakest first — the order the report publishes and the order
        // the study plan is meant to follow.
        foreach ($rows as $index => $row) {
            $this->assertStringContainsString(
                ($index + 1).'. '.$row['label'].' ('.$row['category'].')',
                $facts,
                "The subtopic rows must travel weakest first; row {$index} is out of place.",
            );
        }
    }

    public function test_a_gateway_error_is_explained_in_indonesian_and_stores_nothing(): void
    {
        $this->configureGateway();

        // A bare 503 body: no choices, no text, nothing to show anyone.
        Http::fake(['127.0.0.1:9898/*' => Http::response('', 503)]);

        [$user, $attempt] = $this->gradedPaper();

        // The chain, not two requests: reading the flash off the redirect first
        // would age the bag away before the page that prints it is rendered.
        $this->actingAs($user)
            ->from('/hasil/'.$attempt->id)
            ->followingRedirects()
            ->post('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertOk()
            ->assertSee('Layanan analisa AI menolak permintaan (kode 503)');

        $this->assertDatabaseCount('attempt_analyses', 0);
    }

    /**
     * A gateway that answers "successfully" with nothing in it is not a success.
     * Storing the empty string would show a blank panel that looked like a working
     * feature with nothing to say.
     */
    public function test_an_empty_answer_is_not_stored_as_a_success(): void
    {
        $this->configureGateway();
        Http::fake(['127.0.0.1:9898/*' => Http::response($this->gatewayAnswer("  \n "))]);

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->from('/hasil/'.$attempt->id)
            ->followingRedirects()
            ->post('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertOk()
            ->assertSee('Layanan analisa AI tidak mengirim jawaban');

        $this->assertDatabaseCount('attempt_analyses', 0);
    }

    public function test_a_missing_usage_block_is_stored_as_nothing_rather_than_failing(): void
    {
        $this->configureGateway();

        Http::fake(['127.0.0.1:9898/*' => Http::response([
            'model' => 'deepseek-v4.1-flash',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Analisa tanpa catatan token.']]],
        ])]);

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect(route('hasil.show', $attempt));

        $stored = AttemptAnalysis::query()->firstOrFail();

        $this->assertSame('Analisa tanpa catatan token.', $stored->content);
        $this->assertNull($stored->prompt_tokens);
        $this->assertNull($stored->completion_tokens);
    }

    /**
     * Money, not speed: a second request for a sitting that already has an
     * analysis must not reach the gateway at all. The second queued answer is
     * deliberately different, so a stored row that was somehow replaced shows up
     * as the wrong text rather than as a green test.
     */
    public function test_a_second_generation_is_served_from_the_database_and_costs_nothing(): void
    {
        $this->configureGateway();

        Http::fake(['127.0.0.1:9898/*' => Http::sequence()
            ->push($this->gatewayAnswer('Analisa pertama.'))
            ->push($this->gatewayAnswer('Analisa kedua.'))]);

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect();
        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect();

        Http::assertSentCount(1);

        $this->assertDatabaseCount('attempt_analyses', 1);
        $this->assertSame('Analisa pertama.', AttemptAnalysis::query()->firstOrFail()->content);

        // Reloading the result page is free too, and shows the stored text.
        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Analisa pertama.')
            ->assertDontSee('Analisa kedua.');

        Http::assertSentCount(1);
    }

    public function test_regeneration_replaces_the_stored_analysis_in_place(): void
    {
        $this->configureGateway();

        Http::fake(['127.0.0.1:9898/*' => Http::sequence()
            ->push($this->gatewayAnswer('Analisa pertama.'))
            ->push($this->gatewayAnswer('Analisa kedua.'))]);

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect();
        $this->actingAs($user)
            ->post('/hasil/'.$attempt->id.'/analisa-ai', ['ulangi' => '1'])
            ->assertRedirect(route('hasil.show', $attempt));

        Http::assertSentCount(2);

        // One sitting, one analysis: the row is replaced, never added to.
        $this->assertDatabaseCount('attempt_analyses', 1);
        $this->assertSame('Analisa kedua.', AttemptAnalysis::query()->firstOrFail()->content);

        $this->actingAs($user)->get('/hasil/'.$attempt->id)->assertSee('Analisa kedua.');
    }

    public function test_model_output_with_html_is_escaped_on_the_page(): void
    {
        $this->configureGateway();

        $this->fakeAnswer("Skor kamu <b>bagus</b>.\n\n<script>alert('xss')</script>");

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/analisa-ai')->assertRedirect();

        // The row keeps the raw text...
        $this->assertStringContainsString('<script>', AttemptAnalysis::query()->firstOrFail()->content);

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();

        // ...and the page shows it as text. Escaped once, by Blade, because the
        // model's answer is untrusted input like any other.
        $response->assertSee('&lt;b&gt;bagus&lt;/b&gt;', escape: false);
        $response->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', escape: false);
        $response->assertDontSee('<script>alert', escape: false);
        $response->assertDontSee('<b>bagus</b>', escape: false);
    }

    public function test_the_json_face_answers_json_instead_of_redirecting(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Analisa lewat JSON.');

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->postJson('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('content', 'Analisa lewat JSON.')
            ->assertJsonPath('model', 'deepseek-v4.1-flash');

        $this->assertDatabaseCount('attempt_analyses', 1);
    }

    /** The failure face of the same route, where a redirect would be useless. */
    public function test_the_json_face_reports_a_gateway_failure_as_json(): void
    {
        $this->configureGateway();
        Http::fake(['127.0.0.1:9898/*' => Http::response('', 500)]);

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->postJson('/hasil/'.$attempt->id.'/analisa-ai')
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Layanan analisa AI menolak permintaan (kode 500). Coba lagi sebentar lagi.');

        $this->assertDatabaseCount('attempt_analyses', 0);
    }
}
