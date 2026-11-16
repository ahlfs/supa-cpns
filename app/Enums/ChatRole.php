<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who wrote one turn of a conversation with the gateway.
 *
 * Only two values, and both are dictated by the chat-completions shape the
 * gateway speaks — but they are an enum rather than loose strings because every
 * turn is stored and read back, and a typo in a stored role would silently
 * produce a conversation the model cannot follow.
 *
 * One vocabulary for both conversations in this application: the thread about a
 * single item of a sitting, and the thread about the sitting's own analysis.
 * They store the same shape and are read back the same way, so they name their
 * turns the same way too.
 */
enum ChatRole: string
{
    case User = 'user';

    case Assistant = 'assistant';

    public function isFromTutor(): bool
    {
        return $this === self::Assistant;
    }
}
