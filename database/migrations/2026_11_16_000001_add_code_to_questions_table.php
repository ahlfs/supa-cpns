<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stable identity for a bank item.
     *
     * The importer and the question form write items without one — they are the
     * handwritten path and have nothing to be stable against. The content seeder
     * needs it: it re-runs on every deploy and must update the item it wrote last
     * time instead of appending a second copy, and re-running it is the documented
     * way to pull an edited bank file onto a server.
     *
     * Nullable, so the two handwritten paths stay exactly as they were, and unique,
     * so two items cannot claim the same identity.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('subcategory');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
