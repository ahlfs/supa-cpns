<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\ChatRole;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionChat;
use App\Models\QuestionChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Asking about one item of a finished sitting.
 *
 * What is stored is the point of this class: a thread is kept per item, so a
 * participant who comes back to the page a week later finds their conversation
 * instead of a blank box, and every answer they paid for is still readable
 * without paying again.
 *
 * The write order is deliberate, and it is not a transaction. A gateway call can
 * take a minute; holding a database transaction open across it would pin a write
 * lock on SQLite for that minute and stall the whole site behind one person
 * thinking about a question. So the participant's turn is written first — it has
 * to be, it is part of the prompt — and removed again if the generation fails.
 * The thread is therefore either a complete exchange or nothing, with no window
 * where a stray question sits waiting for an answer that will never come.
 */
final class QuestionTutor
{
    /**
     * The longest question accepted. Long enough for "jelaskan kenapa opsi B
     * salah kalau dilihat dari pasal 28", short enough that the prompt stays
     * dominated by the item rather than by the participant's essay.
     */
    public const MAX_MESSAGE_CHARS = 500;

    /**
     * The ceiling on what is stored for one answer. The prompt asks for three
     * short paragraphs, so this is room to spare rather than a target — but a
     * runaway generation must not be able to fill the table or a page.
     */
    public const MAX_REPLY_CHARS = 3000;

    public function __construct(
        private readonly AnalysisClient $client,
        private readonly QuestionTutorPrompt $prompt,
    ) {}

    /**
     * Whether the panel may be offered at all.
     *
     * The table check is not paranoia: a checkout whose code arrived before
     * `migrate` ran would otherwise turn every result page into an error. One
     * introspection query against a page that already makes a dozen is a fair
     * price for that, and the same guard the analysis panel uses.
     */
    public function isReady(): bool
    {
        $key = config('ai.api_key');

        return (bool) config('ai.enabled')
            && is_string($key)
            && trim($key) !== ''
            && Schema::hasTable('question_chats');
    }

    /**
     * Every thread of this sitting, keyed by the item it is about, so the page can
     * render all of them in one pass instead of a query per row.
     *
     * @return Collection<int, QuestionChat>
     */
    public function threadsFor(ExamAttempt $attempt): Collection
    {
        if (! $this->isReady()) {
            return new Collection;
        }

        return QuestionChat::query()
            ->where('exam_attempt_id', $attempt->id)
            ->with('messages')
            ->get()
            ->keyBy('question_id');
    }

    public function threadFor(ExamAttempt $attempt, Question $question): ?QuestionChat
    {
        if (! $this->isReady()) {
            return null;
        }

        return QuestionChat::query()
            ->where('exam_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->with('messages')
            ->first();
    }

    /**
     * Ask one thing about one item, and store both turns.
     *
     * @throws AnalysisFailed
     */
    public function ask(ExamAttempt $attempt, Question $question, string $message): QuestionChatMessage
    {
        // A sitting that is still open is refused here and not only at the route:
        // being able to ask about an item mid-exam would turn 110 questions into
        // 110 conversations, which is not a tryout.
        if ($attempt->status->isOpen()) {
            throw AnalysisFailed::openSitting();
        }

        $chat = QuestionChat::query()->firstOrCreate([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        $asked = $chat->messages()->create([
            'role' => ChatRole::User,
            'content' => $message,
        ]);

        try {
            $answer = $this->client->send(
                $this->prompt->messages($attempt, $question, $this->history($chat)),
                (int) config('ai.chat_max_tokens', 700),
            );
        } catch (AnalysisFailed $failure) {
            // An unanswered question in the thread would be read back into the
            // next prompt as a participant who asked twice in a row, and shown on
            // the page as a conversation that stalled.
            $asked->delete();

            throw $failure;
        }

        return $chat->messages()->create([
            'role' => ChatRole::Assistant,
            'content' => mb_substr(trim($answer['content']), 0, self::MAX_REPLY_CHARS),
            'model' => $answer['model'],
            'prompt_tokens' => $answer['prompt_tokens'],
            'completion_tokens' => $answer['completion_tokens'],
        ]);
    }

    /**
     * The turns that travel back with the next question: the most recent few, in
     * order, never starting on an answer.
     *
     * A thread has no ceiling of its own — the participant can keep asking — so
     * the request is bounded here instead. Cutting the front can leave an answer
     * with no question above it, which reads to the model as it talking to
     * itself, so a leading answer is dropped along with the turn it belonged to.
     *
     * @return Collection<int, QuestionChatMessage>
     */
    private function history(QuestionChat $chat): Collection
    {
        $limit = max(1, (int) config('ai.chat_history_messages', 10));

        $messages = $chat->messages()->get()->slice(-$limit)->values();

        while ($messages->isNotEmpty() && $messages->first()->isFromTutor()) {
            $messages->shift();
        }

        return $messages;
    }
}
