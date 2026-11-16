<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AttemptAnalysis;
use App\Models\ExamAttempt;

/**
 * Turns one finished sitting into one stored analysis.
 *
 * The caching is the point: a generation costs money and the participant's page
 * can be reloaded freely, so an attempt that already has an analysis is served
 * from the database and the gateway is never asked twice. Only an explicit
 * regeneration (`$force`) pays again, and even then the row is replaced in place,
 * because the unique index on `attempt_id` says a sitting has one analysis, not
 * a pile of them.
 *
 * A sitting that is still open is refused before anything else: its numbers are
 * not frozen yet, so analysing it would produce an opinion about a score that
 * does not exist.
 */
final class AttemptAnalyzer
{
    /**
     * The ceiling on what is stored. The prompt asks for five short sections, so
     * this is room to spare rather than a target — but a runaway answer must not
     * be able to fill the table or a page, and a cut is cheaper than a 413.
     */
    public const MAX_CONTENT_CHARS = 6000;

    public function __construct(private readonly AnalysisClient $client) {}

    /**
     * @throws AnalysisFailed
     */
    public function analyze(ExamAttempt $attempt, bool $force = false): AttemptAnalysis
    {
        if ($attempt->status->isOpen()) {
            throw AnalysisFailed::openSitting();
        }

        if (! $force) {
            $stored = $attempt->analysis()->first();

            if ($stored !== null) {
                return $stored;
            }
        }

        $answer = $this->client->complete($attempt);

        // updateOrCreate against the attempt, not create: a double-tapped button
        // and a deliberate regeneration both have to land on the one row.
        $stored = $attempt->analysis()->updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'content' => $this->cap($answer['content']),
                'model' => $answer['model'],
                'prompt_tokens' => $answer['prompt_tokens'],
                'completion_tokens' => $answer['completion_tokens'],
            ],
        );

        return $stored;
    }

    private function cap(string $content): string
    {
        return mb_substr(trim($content), 0, self::MAX_CONTENT_CHARS);
    }
}
