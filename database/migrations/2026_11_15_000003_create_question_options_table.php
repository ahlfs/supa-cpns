<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 1);
            $table->text('body');
            // Exactly one of these two is set per option: `is_correct` for TWK/TIU,
            // `score` (1-5) for TKP. Which one applies is enforced in the admin
            // FormRequest, not here, because the rule differs per category.
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
