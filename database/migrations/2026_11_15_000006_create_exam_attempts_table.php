<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_package_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress');

            // The clock lives on the server: started_at plus the package duration.
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();

            // Timing per item: the open interval is (current_question_id -> last_activity_at).
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamp('last_activity_at');

            // Result, frozen at submit. Nullable because an open attempt has none.
            $table->unsignedSmallInteger('twk_score')->nullable();
            $table->unsignedSmallInteger('tiu_score')->nullable();
            $table->unsignedSmallInteger('tkp_score')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable();

            // Set only while the attempt is open, so the database itself guarantees
            // one open sitting per participant per package. Nullable, so finished
            // attempts are unconstrained and a participant may retake the package.
            $table->string('active_key')->nullable()->unique();

            $table->timestamps();

            $table->index(['user_id', 'exam_package_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
