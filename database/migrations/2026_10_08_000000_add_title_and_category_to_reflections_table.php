<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets the server keep the title and category of each journal entry,
     * not only its scores and comment. Without these, the "Saved Reflection
     * Entries" list could only live in the browser, so a student who logged
     * in on another device saw nothing. Both are optional so reflections
     * created before this migration keep working (they show as untitled).
     */
    public function up(): void
    {
        Schema::table('reflections', function (Blueprint $table) {
            $table->string('gig_title')->nullable()->after('user_id');
            $table->string('category', 100)->nullable()->after('gig_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reflections', function (Blueprint $table) {
            $table->dropColumn(['gig_title', 'category']);
        });
    }
};
