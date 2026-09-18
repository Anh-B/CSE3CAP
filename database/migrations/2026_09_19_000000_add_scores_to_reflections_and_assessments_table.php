<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an optional `scores` JSON column to both tables so the radar
     * chart can plot the 6 competency scores (contribution, communication,
     * collaboration, agile, continuous, leadership) instead of only the
     * single overall `score`. The original `score` column is kept as-is
     * so nothing that already reads/writes it breaks.
     */
    public function up(): void
    {
        Schema::table('reflections', function (Blueprint $table) {
            $table->json('scores')->nullable()->after('score');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->json('scores')->nullable()->after('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reflections', function (Blueprint $table) {
            $table->dropColumn('scores');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('scores');
        });
    }
};
