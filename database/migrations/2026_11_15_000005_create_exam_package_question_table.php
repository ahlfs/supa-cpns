<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_package_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_package_id')->constrained()->cascadeOnDelete();
            // Deleting a bank item must not silently rewrite the composition of a
            // package that has already been sat: the database refuses the delete
            // instead of cascading the pivot row away, because ExamPackage::isLocked()
            // only means something if the composition cannot shrink underneath it.
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            // No duplicate item and no gap in numbering.
            $table->unique(['exam_package_id', 'question_id']);
            $table->unique(['exam_package_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_package_question');
    }
};
