<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asking about one item of a finished sitting.
 *
 * Two tables rather than one, because a thread is a thing the participant comes
 * back to: the chat carries the pair (sitting, question) and the unique index on
 * it says there is one thread per item, while the messages table carries the
 * turns in order. Reopening a result page later finds the same conversation
 * instead of starting a second one beside it.
 *
 * Cascade from the sitting is deliberate — deleting a participant takes their
 * conversations with them. The question is restricted for the same reason the
 * answer rows are: a bank item that has been talked about must not vanish from
 * under the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'question_id']);
        });

        Schema::create('question_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_chat_id')->constrained()->cascadeOnDelete();
            // 'user' or 'assistant'. A string rather than an enum column because
            // the pair is fixed by the chat-completions shape the gateway speaks.
            $table->string('role', 16);
            $table->text('content');
            // Reported by the gateway, and only for its own answers. A gateway
            // that omits `usage` must not cost the participant their reply, so
            // these stay nullable.
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            // The thread is always read whole and in order.
            $table->index(['question_chat_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_chat_messages');
        Schema::dropIfExists('question_chats');
    }
};
