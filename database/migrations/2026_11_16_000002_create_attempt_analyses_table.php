<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained('exam_attempts')->cascadeOnDelete();
            $table->text('content');
            $table->string('model');
            // Both are whatever the gateway reported. A gateway that omits `usage`
            // must not cost the participant their analysis, so they stay nullable.
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_analyses');
    }
};
