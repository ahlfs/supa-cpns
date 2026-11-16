<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\ChatRole;
use App\Models\AttemptAnalysis;
use App\Models\AttemptAnalysisMessage;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Talking back to the written analysis of a finished sitting.
 *
 * The analysis is generated once and cached; this is what happens when the
 * participant reads it and something in it was not enough. Its opening turn is
 * that stored analysis rather than a row of its own, so the expensive text is
 * never stored twice and a regeneration cannot leave a conversation disagreeing
 * with the text above it.
 *
 * The write order is the item tutor's, and for the same reason: a gateway call can
 * take a minute, and a transaction held across it would pin a write lock on
 * SQLite for that minute and stall the site behind one person thinking. So the
 * participant's turn is written first — it has to be, it is part of the prompt —
 * and removed again if the generation fails. The conversation is therefore either
 * a complete exchange or nothing, with no question sitting under an answer that
 * will never come.
 */
final class AttemptTutor
{
    /**
     * The longest question accepted. Long enough for "jelaskan kenapa rencana
     * belajarnya mulai dari TIU", short enough that the prompt stays dominated by
     * the analysis rather than by the participant's essay.
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
        private readonly AnalysisPrompt $prompt,
    ) {}

    /**
     * Whether the conversation may be offered at all.
     *
     * The table check is the guard the other two panels use: a checkout whose code
     * arrived before `migrate` ran would otherwise turn every result page into an
     * error. One introspection query against a page that already makes a dozen is
     * a fair price for that.
     */
    public function isReady(): bool
    {
        $key = config('ai.api_key');

        return (bool) config('ai.enabled')
            && is_string($key)
            && trim($key) !== ''
            && Schema::hasTable('attempt_analysis_messages');
    }

    /**
     * What the participant asked about their analysis, oldest first, and what was
     * answered. Empty when there is no analysis yet — there is nothing to have
     * asked about.
     *
     * @return Collection<int, AttemptAnalysisMessage>
     */
    public function thread(ExamAttempt $attempt): Collection
    {
        if (! $this->isReady()) {
            return new Collection;
        }

        return $attempt->analysis()->first()?->messages()->get() ?? new Collection;
    }

    /**
     * Ask one thing about the analysis, and store both turns.
     *
     * @throws AnalysisFailed
     */
    public function ask(ExamAttempt $attempt, string $message): AttemptAnalysisMessage
    {
        // Refused here and not only at the route: an analysis of a sitting still
        // in progress is an opinion about a score that does not exist yet.
        if ($attempt->status->isOpen()) {
            throw AnalysisFailed::openSitting();
        }

        $analysis = $attempt->analysis()->first();

        // The conversation hangs off the analysis, so there is nowhere for this to
        // go until one has been written. Answering anyway would spend a
        // generation on a question the participant did not ask.
        if ($analysis === null) {
            throw AnalysisFailed::missingAnalysis();
        }

        $asked = $analysis->messages()->create([
            'role' => ChatRole::User,
            'content' => $message,
        ]);

        try {
            $answer = $this->client->send(
                $this->prompt->followUp($attempt, $analysis, $this->history($analysis)),
                (int) config('ai.chat_max_tokens', 700),
            );
        } catch (AnalysisFailed $failure) {
            // An unanswered question in the conversation would be read back into
            // the next prompt as a participant who asked twice in a row, and shown
            // on the page as a conversation that stalled.
            $asked->delete();

            throw $failure;
        }

        return $analysis->messages()->create([
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
     * A conversation has no ceiling of its own — the participant can keep asking —
     * so the request is bounded here instead. Cutting the front can leave an answer
     * with no question above it, which reads to the model as it talking to itself,
     * so a leading answer is dropped along with the turn it belonged to. The
     * analysis itself is not part of this: it is passed separately, so the cap can
     * never drop the text the questions are about.
     *
     * @return Collection<int, AttemptAnalysisMessage>
     */
    private function history(AttemptAnalysis $analysis): Collection
    {
        $limit = max(1, (int) config('ai.chat_history_messages', 10));

        $messages = $analysis->messages()->get()->slice(-$limit)->values();

        while ($messages->isNotEmpty() && $messages->first()->isFromTutor()) {
            $messages->shift();
        }

        return $messages;
    }
}
