<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            // Attempt history must survive the question bank: deleting a bank item
            // would otherwise erase the answers of finished sittings and leave the
            // frozen totals on exam_attempts describing items that no longer exist,
            // so the database refuses the delete. exam_attempt_id still cascades —
            // an attempt legitimately takes its own answers with it.
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            // Null means the item was opened but no option was chosen.
            $table->foreignId('selected_option_id')->nullable()->constrained('question_options')->nullOnDelete();

            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('score_awarded')->nullable();
            $table->unsignedSmallInteger('seconds_spent')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            // One row per question per attempt: this is what makes auto-save safe to
            // fire repeatedly — repeats collapse instead of stacking.
            $table->unique(['exam_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
    }
};
