<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\ExamAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The one place this application speaks to an AI gateway.
 *
 * The gateway is read from config('ai') — base URL, bearer key, model, timeout,
 * token ceiling and temperature — and is expected to speak the OpenAI
 * chat-completions shape, which is what LAM-Router serves.
 *
 * Two rules hold here and nowhere else. A response with no text in it is not a
 * success: an empty string is thrown away, never stored, because a blank panel
 * would look like a working feature that had nothing to say. And every failure
 * leaves as an `AnalysisFailed` with a sentence a participant can act on, so the
 * controller never has to interpret a status code itself.
 */
final class AnalysisClient
{
    public function __construct(private readonly AnalysisPrompt $prompt) {}

    /**
     * @return array{content: string, model: string, prompt_tokens: ?int, completion_tokens: ?int}
     *
     * @throws AnalysisFailed
     */
    public function complete(ExamAttempt $attempt): array
    {
        return $this->send($this->prompt->messages($attempt));
    }

    /**
     * Any message list, in the shape the gateway speaks.
     *
     * The analysis is now one caller among several: a question thread sends its
     * own messages, with its own ceiling. Keeping the transport here rather than
     * in each feature is what keeps the two rules above true everywhere — the
     * bearer key is read once, and an empty answer is a failure whichever feature
     * asked for it.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, prompt_tokens: ?int, completion_tokens: ?int}
     *
     * @throws AnalysisFailed
     */
    public function send(array $messages, ?int $maxTokens = null, ?float $temperature = null): array
    {
        $baseUrl = $this->setting('base_url');
        $apiKey = $this->setting('api_key');
        $model = $this->setting('model');

        if (! config('ai.enabled') || $baseUrl === null || $apiKey === null || $model === null) {
            throw AnalysisFailed::notConfigured();
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('ai.timeout', 60))
                ->post($this->endpoint($baseUrl), [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens ?? (int) config('ai.max_tokens', 900),
                    'temperature' => $temperature ?? (float) config('ai.temperature', 0.4),
                ]);
        } catch (ConnectionException $failure) {
            // Refused, unresolvable, or past the timeout: all the same to a
            // participant, and all of them worth retrying by hand.
            throw AnalysisFailed::unreachable($failure);
        }

        if ($response->failed()) {
            throw AnalysisFailed::gateway($response->status());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw AnalysisFailed::emptyAnswer();
        }

        return [
            'content' => $content,
            // The gateway names the model that actually answered; the configured
            // name is the fallback for a gateway that leaves the field out. Which
            // one wrote the answer is worth keeping accurate.
            'model' => $this->name($response) ?? $model,
            'prompt_tokens' => $this->tokens($response->json('usage.prompt_tokens')),
            'completion_tokens' => $this->tokens($response->json('usage.completion_tokens')),
        ];
    }

    private function endpoint(string $baseUrl): string
    {
        return rtrim($baseUrl, '/').'/chat/completions';
    }

    /** A configured value that is present and not blank, or null. */
    private function setting(string $key): ?string
    {
        $value = config('ai.'.$key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function name(Response $response): ?string
    {
        $model = $response->json('model');

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    /** Gateways disagree about whether usage counts come back as ints or strings. */
    private function tokens(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
