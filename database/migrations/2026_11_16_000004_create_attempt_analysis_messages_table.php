<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talking back to the written analysis of a sitting.
 *
 * The analysis itself is a column on `attempt_analyses` — one generation per
 * sitting, cached, kept so the page can be reloaded for free — and it is the
 * opening turn of this conversation rather than a row in this table. Only what
 * comes after it lands here, so the expensive text is not stored twice and
 * cannot drift between the two copies.
 *
 * The row is reused in place when an analysis is regenerated (the unique index
 * on `attempt_analyses` sees to that), and the conversation deliberately stays
 * with it: what the participant asked about is their own numbers, which a
 * regeneration does not change, only the wording around them. Cascading from the
 * analysis is for the case where it is gone entirely — a thread whose opening
 * turn no longer exists is a wall of answers to a question nobody can see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_analysis_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_analysis_id')->constrained()->cascadeOnDelete();
            // 'user' or 'assistant', the same two the item thread stores.
            $table->string('role', 16);
            $table->text('content');
            // Reported by the gateway, and only for its own answers. A gateway
            // that omits `usage` must not cost the participant their reply, so
            // these stay nullable.
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            // The conversation is always read whole and in order.
            $table->index(['attempt_analysis_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_analysis_messages');
    }
};
