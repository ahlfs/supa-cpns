<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\QuestionChat;
use App\Models\User;
use App\Services\Ai\QuestionTutor;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

/**
 * Asking the tutor about one item of a finished sitting.
 *
 * Four properties carry this feature, and each is asserted more than once because
 * each is a way it could quietly hurt somebody. The item and the participant's
 * own answer must travel, or the answer is generic advice instead of an
 * explanation of the question in front of them. The prompt must hold the tutor
 * inside that one item, because a page a candidate reads to decide what to study
 * is no place for an invented article number. Nothing may be stored for a
 * generation that failed, or the thread shows a question that will never be
 * answered. And the model's text has to come out as text — it is the only string
 * in this application nobody here wrote.
 */
final class QuestionChatTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private const BASE_URL = 'http://127.0.0.1:9898/v1';

    private const KEY = 'lam-router-key';

    private const MODEL = 'deepseek-v3.2';

    private function configureGateway(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.base_url', self::BASE_URL);
        config()->set('ai.api_key', self::KEY);
        config()->set('ai.model', self::MODEL);
        config()->set('ai.timeout', 30);
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

    private function questionOf(ExamAttempt $attempt, string $category): Question
    {
        return $attempt->package->questions()->where('questions.category', $category)->firstOrFail();
    }

    /**
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
            'usage' => ['prompt_tokens' => 640, 'completion_tokens' => 180],
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

    /** The system message: the rules and the item, which must never be dropped. */
    private function sentSystemMessage(int $index = 0): string
    {
        return (string) ($this->sentMessages($index)[0]['content'] ?? '');
    }

    public function test_the_offer_is_absent_when_the_feature_is_disabled(): void
    {
        $this->configureGateway();
        config()->set('ai.enabled', false);

        [$user, $attempt] = $this->gradedPaper();

        $page = (string) $this->actingAs($user)->get('/hasil/'.$attempt->id)->getContent();

        $this->assertStringNotContainsString('Tanya SUPA Bot tentang soal ini', $page);
        $this->assertStringNotContainsString('data-chat-form', $page);
        // The review list itself is untouched: this is absence, not a broken page.
        $this->assertStringContainsString('Pembahasan', $page);
    }

    public function test_the_offer_is_absent_when_no_gateway_key_is_configured(): void
    {
        $this->configureGateway();
        config()->set('ai.api_key', null);

        [$user, $attempt] = $this->gradedPaper();
        $question = $this->questionOf($attempt, 'twk');

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertDontSee('Tanya SUPA Bot tentang soal ini');

        // And the page itself is not reachable either, whatever the list says.
        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya')
            ->assertNotFound();
    }

    /**
     * A checkout whose code arrived before `migrate` ran, which is what the live
     * host is between a deploy and its migration. The offer must vanish rather
     * than take the results page down with it.
     */
    public function test_the_offer_is_absent_when_the_tables_do_not_exist_yet(): void
    {
        $this->configureGateway();

        [$user, $attempt] = $this->gradedPaper();

        Schema::drop('question_chat_messages');
        Schema::drop('question_chats');

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $response->assertDontSee('Tanya SUPA Bot tentang soal ini');
        $response->assertSee('Skor total');
    }

    public function test_a_guest_cannot_open_or_ask(): void
    {
        $this->configureGateway();
        Http::fake();

        [, $attempt] = $this->gradedPaper();
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->get($url)->assertRedirect(route('login'));
        $this->post($url, ['message' => 'Kenapa jawabanku salah?'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('question_chat_messages', 0);
        Http::assertNothingSent();
    }

    public function test_another_participants_item_cannot_be_asked_about(): void
    {
        $this->configureGateway();
        Http::fake();

        [, $attempt] = $this->gradedPaper();
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->actingAs($stranger)->post($url, ['message' => 'Kenapa?'])->assertForbidden();

        $this->assertDatabaseCount('question_chat_messages', 0);
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

        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->get($url)->assertRedirect(route('tryout.room', $attempt));
        $this->actingAs($user)->post($url, ['message' => 'Kenapa?'])->assertRedirect(route('tryout.room', $attempt));

        $this->assertDatabaseCount('question_chat_messages', 0);
        Http::assertNothingSent();
    }

    /**
     * Asking spends money, so the route refuses a question that is not on this
     * paper however it is reached.
     */
    public function test_a_question_outside_the_paper_is_not_found(): void
    {
        $this->configureGateway();
        Http::fake();

        [$user, $attempt] = $this->gradedPaper();
        $outside = Question::factory()->create();

        $url = '/hasil/'.$attempt->id.'/soal/'.$outside->id.'/tanya';

        $this->actingAs($user)->get($url)->assertNotFound();
        $this->actingAs($user)->post($url, ['message' => 'Kenapa?'])->assertNotFound();

        $this->assertDatabaseCount('question_chat_messages', 0);
        Http::assertNothingSent();
    }

    public function test_the_page_shows_the_item_its_options_and_its_key(): void
    {
        $this->configureGateway();
        Http::fake();

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');

        $response = $this->actingAs($user)
            ->get('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya');

        $response->assertOk();
        $response->assertSee('Tanya SUPA Bot tentang soal ini');
        $response->assertSee($question->stem);
        $response->assertSee($question->subcategoryLabel());

        foreach ($question->options as $option) {
            $response->assertSee($option->body);
        }

        // The key is pointed out, the participant's own pick is marked, and the
        // chip that asks the obvious question is offered.
        $response->assertSee('kunci');
        $response->assertSee('jawabanmu');
        $response->assertSee('Kenapa jawabanku salah?');

        // Nothing was generated by looking at the page.
        Http::assertNothingSent();
        $this->assertDatabaseCount('question_chat_messages', 0);
    }

    public function test_a_tkp_item_offers_the_questions_tkp_actually_raises(): void
    {
        $this->configureGateway();
        Http::fake();

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'tkp');

        $response = $this->actingAs($user)
            ->get('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya');

        $response->assertOk();
        // Every option is defensible there, so weights are what the page shows
        // and what the chips ask about — never a key, and never "salah".
        $response->assertSee('bobot');
        $response->assertDontSee('kunci');
        $response->assertSee('Kenapa bobot opsi ini beda-beda?');
        $response->assertDontSee('Kenapa jawabanku salah?');
    }

    public function test_asking_stores_both_turns_and_the_page_prints_them(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Pilihanmu kurang tepat karena soal ini menguji hal lain.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)
            ->post($url, ['message' => 'Kenapa jawabanku salah?'])
            ->assertRedirect(route('hasil.tanya', [
                'attempt' => $attempt,
                'question' => $question,
                'filter' => 'all',
            ]));

        $this->assertDatabaseCount('question_chats', 1);
        $this->assertDatabaseCount('question_chat_messages', 2);

        $chat = QuestionChat::query()->firstOrFail();
        $messages = $chat->messages()->get();

        $this->assertSame('user', $messages[0]->role->value);
        $this->assertSame('Kenapa jawabanku salah?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role->value);
        // The gateway names the model that actually answered; that is the name
        // worth keeping, the configured one is only a fallback.
        $this->assertSame('deepseek-v4.1-flash', $messages[1]->model);
        $this->assertSame(640, $messages[1]->prompt_tokens);
        $this->assertSame(180, $messages[1]->completion_tokens);
        $this->assertNull($messages[0]->model);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('Kenapa jawabanku salah?')
            ->assertSee('Pilihanmu kurang tepat karena soal ini menguji hal lain.')
            ->assertSee('SUPA Bot')
            ->assertSee('Kamu');
    }

    /**
     * The payload is the feature: without the item, its options and the
     * participant's own answer, the reply can only be generic advice.
     */
    public function test_the_request_carries_the_item_the_key_and_the_participants_own_answer(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Oke, ini penjelasannya.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $answer = $attempt->answers()->where('question_id', $question->id)->firstOrFail();
        $chosen = $question->options->firstWhere('id', $answer->selected_option_id);
        $key = $question->options->firstWhere('is_correct', true);

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya', [
            'message' => 'Kenapa pilihanku salah?',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE_URL.'/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY));

        $messages = $this->sentMessages();

        // One system message and one user turn: the item is not repeated per turn.
        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('Kenapa pilihanku salah?', $messages[1]['content']);

        $system = $this->sentSystemMessage();

        $this->assertStringContainsString($question->stem, $system);
        $this->assertStringContainsString($question->subcategoryLabel(), $system);
        $this->assertStringContainsString('kunci jawaban', $system);
        $this->assertStringContainsString($key->body, $system);
        $this->assertStringContainsString($question->explanation, $system);

        // The one thing only this feature knows.
        $this->assertStringContainsString('Pilihan peserta: '.$chosen->label, $system);
        $this->assertStringContainsString('Hasil: salah', $system);
        $this->assertStringContainsString(
            'poin '.$answer->score_awarded.' dari '.$question->category->scorePerCorrect(),
            $system,
        );

        // Every option travels, not only the two that matter.
        foreach ($question->options as $option) {
            $this->assertStringContainsString($option->label.'. '.$option->body, $system);
        }
    }

    public function test_a_tkp_item_travels_with_its_weights_and_without_a_key(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Oke.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'tkp');
        $answer = $attempt->answers()->where('question_id', $question->id)->firstOrFail();

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya', [
            'message' => 'Kenapa bobotnya beda?',
        ]);

        $system = $this->sentSystemMessage();

        // TKP has no right answer at all: the weights are the whole item, and
        // saying "kunci" here would teach the participant something false.
        $this->assertStringNotContainsString('kunci jawaban', $system);
        $this->assertStringContainsString('bobot 5 dari 5', $system);
        $this->assertStringContainsString('tidak punya jawaban salah', $system);
        $this->assertStringContainsString(
            'memberi '.$answer->score_awarded.' dari '.$question->category->perItemMaximum().' poin',
            $system,
        );
    }

    /**
     * The scope lock, which is the whole reason this page is safe to put in front
     * of a candidate: the tutor may only work on the item it was handed.
     */
    public function test_the_system_message_holds_the_tutor_inside_this_one_item(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Oke.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya', [
            'message' => 'Buatkan skrip Python untuk menghitung ini.',
        ]);

        $system = $this->sentSystemMessage();

        $this->assertStringContainsString('HANYA tentang soal itu', $system);
        $this->assertStringContainsString('membuatkan skrip atau kode program', $system);
        $this->assertStringContainsString('Maaf, aku hanya bisa membahas soal ini.', $system);
        // Legal questions are the sharpest edge of this domain: an invented
        // article number is worse than no answer.
        $this->assertStringContainsString('Jangan mengarang pasal', $system);
        $this->assertStringContainsString('katakan bahwa kamu tidak yakin', $system);
        $this->assertStringContainsString('bahasa Indonesia', $system);
        $this->assertStringContainsString('Jawab hanya berdasarkan data soal di atas.', $system);
    }

    public function test_a_thread_remembers_what_was_asked_before(): void
    {
        $this->configureGateway();

        Http::fake(['127.0.0.1:9898/*' => Http::sequence()
            ->push($this->gatewayAnswer('Jawaban pertama.'))
            ->push($this->gatewayAnswer('Jawaban kedua.'))]);

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->post($url, ['message' => 'Pertanyaan pertama.']);
        $this->actingAs($user)->post($url, ['message' => 'Pertanyaan kedua.']);

        // One thread for the item, four turns, never a second thread beside it.
        $this->assertDatabaseCount('question_chats', 1);
        $this->assertDatabaseCount('question_chat_messages', 4);

        $second = $this->sentMessages(1);

        // System, then the whole conversation oldest first, ending on the new one.
        $this->assertCount(4, $second);
        $this->assertSame('system', $second[0]['role']);
        $this->assertSame('Pertanyaan pertama.', $second[1]['content']);
        $this->assertSame('Jawaban pertama.', $second[2]['content']);
        $this->assertSame('Pertanyaan kedua.', $second[3]['content']);
    }

    /**
     * A thread has no ceiling of its own — a participant can keep asking — so the
     * request is bounded instead. Cutting the front must not leave the model
     * reading an answer with no question above it.
     */
    public function test_the_history_travels_capped_and_starts_on_a_question(): void
    {
        $this->configureGateway();
        config()->set('ai.chat_history_messages', 4);

        Http::fake(['127.0.0.1:9898/*' => Http::sequence()
            ->push($this->gatewayAnswer('Jawaban pertama.'))
            ->push($this->gatewayAnswer('Jawaban kedua.'))
            ->push($this->gatewayAnswer('Jawaban ketiga.'))]);

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->post($url, ['message' => 'Pertanyaan pertama.']);
        $this->actingAs($user)->post($url, ['message' => 'Pertanyaan kedua.']);
        $this->actingAs($user)->post($url, ['message' => 'Pertanyaan ketiga.']);

        // The thread kept everything...
        $this->assertDatabaseCount('question_chat_messages', 6);

        // ...but the request carried the tail of it: the sheet is
        // [A1, U2, A2, U3], and the orphaned answer at the front is dropped.
        $last = $this->sentMessages(2);

        $this->assertCount(4, $last);
        $this->assertSame('system', $last[0]['role']);
        $this->assertSame('Pertanyaan kedua.', $last[1]['content']);
        $this->assertSame('Jawaban kedua.', $last[2]['content']);
        $this->assertSame('Pertanyaan ketiga.', $last[3]['content']);

        // The item still travels, even though the earliest turns did not.
        $this->assertStringContainsString($question->stem, (string) $last[0]['content']);
    }

    public function test_a_gateway_failure_leaves_no_half_exchange_behind(): void
    {
        $this->configureGateway();
        Http::fake(['127.0.0.1:9898/*' => Http::response('', 503)]);

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)
            ->from($url)
            ->followingRedirects()
            ->post($url, ['message' => 'Kenapa jawabanku salah?'])
            ->assertOk()
            ->assertSee('Layanan analisa AI menolak permintaan (kode 503)');

        // A question nobody will ever answer must not sit in the thread, and must
        // not be read back into the next prompt as somebody asking twice.
        $this->assertDatabaseCount('question_chat_messages', 0);
        $this->assertDatabaseCount('question_chats', 1);
    }

    public function test_an_empty_question_is_refused_without_calling_the_gateway(): void
    {
        $this->configureGateway();
        Http::fake();

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)
            ->from($url)
            ->post($url, ['message' => '   '])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('question_chat_messages', 0);
        Http::assertNothingSent();
    }

    public function test_an_overlong_question_is_refused(): void
    {
        $this->configureGateway();
        Http::fake();

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)
            ->from($url)
            ->post($url, ['message' => str_repeat('a', QuestionTutor::MAX_MESSAGE_CHARS + 1)])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('question_chat_messages', 0);
        Http::assertNothingSent();
    }

    /** Pressing a suggested question sends that question, not an empty box. */
    public function test_a_suggested_question_is_asked_when_its_chip_is_pressed(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Oke.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->post($url, ['saran' => 'Jelaskan konsep yang diuji soal ini']);

        $messages = $this->sentMessages();

        $this->assertSame('Jelaskan konsep yang diuji soal ini', $messages[1]['content']);
        $this->assertSame('Jelaskan konsep yang diuji soal ini', QuestionChat::query()->firstOrFail()->messages()->firstOrFail()->content);
    }

    /**
     * A chip pressed after something was already typed still asks the suggested
     * question: clicking it was the later, deliberate act, and it submits under
     * its own field name so the two can never be confused for each other.
     */
    public function test_a_chip_pressed_after_typing_still_asks_the_suggested_question(): void
    {
        $this->configureGateway();
        $this->fakeAnswer('Oke.');

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya', [
            'message' => 'Pertanyaanku sendiri.',
            'saran' => 'Kenapa jawabanku salah?',
        ]);

        $this->assertSame('Kenapa jawabanku salah?', $this->sentMessages()[1]['content']);
    }

    public function test_model_output_with_html_is_escaped_on_the_page(): void
    {
        $this->configureGateway();
        $this->fakeAnswer("<script>alert('xss')</script>\n\nSkor kamu <b>aman</b>.");

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->post($url, ['message' => 'Kenapa?']);

        // The row keeps the raw text...
        $this->assertStringContainsString('<script>', QuestionChat::query()->firstOrFail()->messages()->get()->last()->content);

        // ...and the page shows it as text. Escaped once, by Blade, because the
        // model's answer is untrusted input like any other.
        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', escape: false);
        $response->assertDontSee('<script>alert', escape: false);
        $response->assertDontSee('<b>aman</b>', escape: false);
    }

    public function test_markdown_emphasis_the_model_adds_anyway_does_not_reach_the_page(): void
    {
        $this->configureGateway();
        $this->fakeAnswer("**Pilihanmu belum tepat.**\n\n## Yang diuji\n\nSoal ini menguji **nasionalisme**.");

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');
        $url = '/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya';

        $this->actingAs($user)->post($url, ['message' => 'Kenapa?']);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
        $response->assertSee('Pilihanmu belum tepat.');
        $response->assertSee('Soal ini menguji nasionalisme.');
        $response->assertDontSee('**', escape: false);
        $response->assertDontSee('##', escape: false);
    }

    public function test_an_answer_that_runs_away_is_capped_before_it_is_stored(): void
    {
        $this->configureGateway();
        $this->fakeAnswer(str_repeat('panjang ', 3000));

        [$user, $attempt] = $this->gradedPaper('worst');
        $question = $this->questionOf($attempt, 'twk');

        $this->actingAs($user)->post('/hasil/'.$attempt->id.'/soal/'.$question->id.'/tanya', [
            'message' => 'Kenapa?',
        ]);

        $stored = QuestionChat::query()->firstOrFail()->messages()->get()->last();

        $this->assertSame(QuestionTutor::MAX_REPLY_CHARS, mb_strlen($stored->content));
    }

    public function test_the_chat_page_is_not_reachable_from_a_guessed_url_of_another_sitting(): void
    {
        $this->configureGateway();
        Http::fake();

        [, $first] = $this->gradedPaper('worst');
        $question = $this->questionOf($first, 'twk');

        // A second participant, with a sitting of their own, guessing the first
        // one's item.
        [$other, $otherAttempt] = $this->gradedPaper('worst');

        $this->actingAs($other)
            ->get('/hasil/'.$first->id.'/soal/'.$question->id.'/tanya')
            ->assertForbidden();

        // Their own item in their own sitting is of course fine.
        $this->actingAs($other)
            ->get('/hasil/'.$otherAttempt->id.'/soal/'.$this->questionOf($otherAttempt, 'twk')->id.'/tanya')
            ->assertOk();

        Http::assertNothingSent();
    }
}
