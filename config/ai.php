<?php

declare(strict_types=1);

/*
 * The AI analysis on the score page.
 *
 * The model is reached through LAM-Router on the loopback interface, speaking
 * the OpenAI chat-completions shape. Everything the feature needs to know is
 * here rather than spread through the service, so pointing it at a different
 * gateway or model is an .env change and not a code change.
 *
 * `enabled` exists because the panel has to be honest: with no key configured
 * the page shows nothing at all rather than a button that fails when pressed.
 */
return [
    'enabled' => (bool) env('AI_ANALYSIS_ENABLED', false),

    'base_url' => env('AI_ANALYSIS_BASE_URL', 'http://127.0.0.1:9898/v1'),

    'api_key' => env('AI_ANALYSIS_API_KEY'),

    'model' => env('AI_ANALYSIS_MODEL', 'river/deepseek-ai/DeepSeek-V4.1-Flash'),

    /*
     * The assistant the participant is talking to, by name. One place, because it
     * is printed by two different panels — the thread about a single item, and
     * the conversation about the sitting's analysis — and a product name spelled
     * in two files is a product name spelled wrong half the time.
     */
    'bot_name' => (string) (env('AI_BOT_NAME') ?: 'SUPA Bot'),

    /*
     * A person is waiting on this request, so the ceiling is well under the
     * point where they would reload instead. DeepSeek answers a prompt this size
     * in a few seconds; a timeout this long only fires when the gateway is
     * unreachable, and that has to surface as a message, not a hung page.
     */
    'timeout' => (int) env('AI_ANALYSIS_TIMEOUT', 90),

    /*
     * Output ceiling. The analysis is a few short sections; the cap keeps a
     * runaway generation from billing for thousands of tokens nobody reads.
     */
    'max_tokens' => (int) env('AI_ANALYSIS_MAX_TOKENS', 1200),

    'temperature' => (float) env('AI_ANALYSIS_TEMPERATURE', 0.3),

    /*
     * Asking about one item, or asking back about the analysis of a sitting. A
     * reply here is a short explanation of something already on screen, not a
     * study plan, so the ceiling is lower than the analysis's — there is nothing
     * for a longer answer to add, and its cost is paid per conversation rather
     * than per paper.
     */
    'chat_max_tokens' => (int) env('AI_ANALYSIS_CHAT_MAX_TOKENS', 700),

    /*
     * How many stored turns of a conversation travel back with the next
     * question. A conversation can grow without limit — the participant can keep
     * asking — so the request has to be bounded by something other than their
     * patience.
     */
    'chat_history_messages' => (int) env('AI_ANALYSIS_CHAT_HISTORY', 10),
];
