<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_subtopics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('subcategory');
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('wrong')->default(0);
            $table->unsignedSmallInteger('blank')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'category', 'subcategory']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_subtopics');
    }
};
