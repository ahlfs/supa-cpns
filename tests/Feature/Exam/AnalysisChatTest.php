<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Models\AttemptAnalysis;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

/**
 * Asking back about the written analysis of a finished sitting.
 *
 * Three properties carry this feature. The opening turn must be the stored
 * analysis itself, because a question about a text the model cannot see is a
 * question answered from nothing. The rules must be the follow-up ones and not
 * the analysis ones: the analysis prompt asks for five sections and a study plan,
 * which is the wrong answer to "why is TIU my weakest subtest", and the drift the
 * participant is protected from — a law question that becomes "write me a script"
 * — is refused by a rule that has to actually be in the system message. And
 * nothing may be stored for a generation that failed, or the conversation shows a
 * question under an answer that will never come.
 *
 * What is deliberately not asserted here is the geometry: which side a bubble
 * sits on is measured in a real browser by tools/question-chat-smoke.mjs, and a
 * PHP test that counts a margin utility to mean "on the right" is a test that
 * breaks the next time somebody restyles the page.
 */
final class AnalysisChatTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private const BASE_URL = 'http://127.0.0.1:9898/v1';

    private const MODEL = 'deepseek-v4.1-flash';

    private function configureGateway(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.base_url', self::BASE_URL);
        config()->set('ai.api_key', 'lam-router-key');
        config()->set('ai.model', self::MODEL);
        config()->set('ai.timeout', 30);
        config()->set('ai.bot_name', 'SUPA Bot');
        config()->set('ai.chat_max_tokens', 700);
        config()->set('ai.chat_history_messages', 10);
    }

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function gradedPaper(string $strategy = 'worst'): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, $strategy);

        return [$user, app(ExamGrader::class)->grade($attempt)];
    }

    private function withAnalysis(ExamAttempt $attempt, string $content = 'TIU adalah subtes terlemahmu. Mulai dari deret angka.'): AttemptAnalysis
    {
        return $attempt->analysis()->create([
            'content' => $content,
            'model' => self::MODEL,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gatewayAnswer(string $content): array
    {
        return [
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'model' => self::MODEL,
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 810, 'completion_tokens' => 150],
        ];
    }

    private function fakeAnswer(string $content): void
    {
        Http::fake(['127.0.0.1:9898/*' => Http::response($this->gatewayAnswer($content))]);
    }

    /**
     * The messages array of the request that was actually sent.
     *
     * @return list<array{role: string, content: string}>
     */
    private function sentMessages(int $index = 0): array
    {
        $captured = [];

        Http::assertSent(function (Request $request) use (&$captured): bool {
            $captured[] = $request->data();

            return true;
        });

        return $captured[$index]['messages'] ?? [];
    }

    private function askUrl(ExamAttempt $attempt): string
    {
        return '/hasil/'.$attempt->id.'/analisa-ai/tanya';
    }

    /**
     * The box is a conversation, and a conversation needs something to be about.
     * Before the analysis exists there is nothing to ask back about, so the page
     * offers only the button that writes it.
     */
    public function test_the_conversation_appears_only_after_an_analysis_exists(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Buat analisa AI')
            ->assertDontSee('Tanya lanjutan')
            ->assertDontSee('data-chat-form');

        $this->withAnalysis($attempt);

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Tanya lanjutan')
            ->assertSee('SUPA Bot')
            // The request that produced the analysis, and the analysis itself, are
            // the first exchange of the conversation.
            ->assertSee('Analisa hasil tryout saya.')
            ->assertSee('TIU adalah subtes terlemahmu.');
    }

    public function test_the_panel_is_absent_when_the_gateway_is_not_configured(): void
    {
        $this->configureGateway();
        config()->set('ai.enabled', false);

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);

        $page = (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent();

        $this->assertStringNotContainsString('Tanya lanjutan', $page);
        $this->assertStringNotContainsString('data-chat-form', $page);
        // The page itself is whole: this is absence, not a broken panel.
        $this->assertStringContainsString('Skor total', $page);
    }

    public function test_a_question_asked_back_is_stored_and_printed_on_both_sides(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);
        $this->fakeAnswer('Karena deret angka paling sering salah. Latih 20 soal deret tiap hari.');

        $this->actingAs($user)
            ->post($this->askUrl($attempt), ['message' => 'Kenapa TIU jadi yang paling lemah?'])
            ->assertRedirect(route('hasil.show', $attempt).'#analisa');

        $messages = $attempt->analysis()->first()->messages()->get();

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role->value);
        $this->assertSame('Kenapa TIU jadi yang paling lemah?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role->value);
        $this->assertSame('Karena deret angka paling sering salah. Latih 20 soal deret tiap hari.', $messages[1]->content);
        // The gateway names the model that actually answered; that is the name
        // worth keeping, the configured one is only a fallback.
        $this->assertSame(self::MODEL, $messages[1]->model);
        $this->assertSame(810, $messages[1]->prompt_tokens);
        $this->assertSame(150, $messages[1]->completion_tokens);
        $this->assertNull($messages[0]->model);

        $page = (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent();

        $this->assertStringContainsString('Kenapa TIU jadi yang paling lemah?', $page);
        $this->assertStringContainsString('Karena deret angka paling sering salah.', $page);
        $this->assertStringContainsString('SUPA Bot', $page);
        $this->assertStringContainsString('Kamu', $page);

        // Two on each side: the request and the analysis, then the question and
        // its answer. Which side each sits on is the browser's business; that they
        // are distinguishable at all is this page's.
        $this->assertSame(2, substr_count($page, 'data-chat-turn="assistant"'));
        $this->assertSame(2, substr_count($page, 'data-chat-turn="user"'));
    }

    /**
     * The payload is the feature: without the sitting's own numbers and the text
     * the question is about, the answer can only be generic advice.
     */
    public function test_the_request_carries_the_facts_the_analysis_and_the_question(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt, 'Analisa: TKP perlu perhatian paling besar.');
        $this->fakeAnswer('Jawaban.');

        $this->actingAs($user)->post($this->askUrl($attempt), ['message' => 'Bagian mana yang paling mendesak?']);

        $sent = $this->sentMessages();

        $this->assertCount(4, $sent);
        $this->assertSame('system', $sent[0]['role']);
        $this->assertSame('user', $sent[1]['role']);
        $this->assertStringContainsString('HASIL TRYOUT SKD CPNS', $sent[1]['content']);
        $this->assertStringContainsString('Skor total:', $sent[1]['content']);
        $this->assertStringContainsString('SUBTOPIK', $sent[1]['content']);
        // The stored analysis travels as the assistant's own opening turn.
        $this->assertSame('assistant', $sent[2]['role']);
        $this->assertSame('Analisa: TKP perlu perhatian paling besar.', $sent[2]['content']);
        $this->assertSame('user', $sent[3]['role']);
        $this->assertSame('Bagian mana yang paling mendesak?', $sent[3]['content']);
    }

    /**
     * The follow-up rules are their own, and the difference matters: the analysis
     * prompt asks for five sections and a study plan, which is a wall of text when
     * somebody asked a question.
     */
    public function test_the_follow_up_rules_are_not_the_analysis_rules(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);
        $this->fakeAnswer('Jawaban.');

        $this->actingAs($user)->post($this->askUrl($attempt), ['message' => 'Kenapa?']);

        $system = $this->sentMessages()[0]['content'];

        $this->assertStringContainsString('namamu SUPA Bot', $system);
        $this->assertStringContainsString('Pakai HANYA angka dan nama', $system);
        // The refusal the participant asked for, in the words the page promises.
        $this->assertStringContainsString('Maaf, aku hanya bisa membahas hasil tryout ini.', $system);
        $this->assertStringContainsString('tanpa **, ##, tanda bintang', $system);
        // And not the analysis-shaped instruction it must not inherit.
        $this->assertStringNotContainsString('rencana belajar singkat dan berurutan', $system);
        $this->assertStringNotContainsString('paling banyak lima bagian pendek', $system);
    }

    public function test_a_failed_generation_leaves_no_half_exchange(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);

        Http::fake(['127.0.0.1:9898/*' => Http::response(['error' => ['message' => 'upstream is down']], 500)]);

        $this->actingAs($user)
            ->post($this->askUrl($attempt), ['message' => 'Kenapa TIU lemah?'])
            ->assertRedirect(route('hasil.show', $attempt).'#analisa')
            ->assertSessionHas('error');

        // An unanswered question in the conversation would be read back into the
        // next prompt as a participant who asked twice in a row.
        $this->assertDatabaseCount('attempt_analysis_messages', 0);

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('Layanan analisa AI menolak permintaan', false);
    }

    public function test_a_question_about_somebody_elses_sitting_is_refused(): void
    {
        $this->configureGateway();

        [, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post($this->askUrl($attempt), ['message' => 'Kenapa?'])
            ->assertForbidden();

        $this->assertDatabaseCount('attempt_analysis_messages', 0);
    }

    /**
     * The conversation hangs off the analysis, so asking before there is one has
     * nowhere to go — and the refusal says which button writes it.
     */
    public function test_a_question_without_an_analysis_is_refused_with_a_way_forward(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();

        $this->actingAs($user)
            ->post($this->askUrl($attempt), ['message' => 'Kenapa?'])
            ->assertRedirect(route('hasil.show', $attempt).'#analisa')
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'Buat analisanya dulu'));

        $this->assertDatabaseCount('attempt_analysis_messages', 0);
    }

    /**
     * Pressing Kirim on an empty box is the one rejection reachable by hand, and a
     * page that silently does nothing reads as a broken feature.
     */
    public function test_an_empty_question_comes_back_as_a_message_on_the_page(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $this->withAnalysis($attempt);

        // Following the redirect rather than asserting on the bag: the bag is not
        // what the participant sees, and a separate request would not carry it.
        // This walks the page the way they do — press Kirim, land back here.
        $this->actingAs($user)
            ->from('/hasil/'.$attempt->id)
            ->followingRedirects()
            ->post($this->askUrl($attempt), ['message' => '   '])
            ->assertOk()
            ->assertSee('Tulis dulu pertanyaanmu tentang analisa ini.');

        $this->assertDatabaseCount('attempt_analysis_messages', 0);
    }

    public function test_the_conversation_carries_only_the_recent_turns_and_never_starts_on_an_answer(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();
        $analysis = $this->withAnalysis($attempt);
        $this->fakeAnswer('Jawaban terakhir.');

        // Six complete exchanges already behind it, so the seventh is the one the
        // history cap has to cut through the middle of.
        for ($turn = 1; $turn <= 6; $turn++) {
            $analysis->messages()->create(['role' => 'user', 'content' => 'Pertanyaan '.$turn]);
            $analysis->messages()->create(['role' => 'assistant', 'content' => 'Jawaban '.$turn]);
        }

        $this->actingAs($user)->post($this->askUrl($attempt), ['message' => 'Pertanyaan 7']);

        $sent = $this->sentMessages();

        // The rules, the facts and the analysis, then at most ten stored turns —
        // never more, and never headed by an answer with no question above it.
        $this->assertSame('system', $sent[0]['role']);
        $this->assertSame('user', $sent[1]['role']);
        $this->assertSame('assistant', $sent[2]['role']);
        $this->assertLessThanOrEqual(13, count($sent));
        $this->assertSame('user', $sent[3]['role']);
        $this->assertSame('Pertanyaan 7', end($sent)['content']);
    }

    /** A conversation only goes where the participant can see it. */
    public function test_the_tutor_is_not_offered_on_a_sitting_that_is_still_running(): void
    {
        $this->configureGateway();

        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $this->actingAs($user)
            ->post($this->askUrl($attempt), ['message' => 'Kenapa?'])
            ->assertRedirect(route('tryout.room', $attempt));

        $this->assertDatabaseCount('attempt_analysis_messages', 0);
    }
}
