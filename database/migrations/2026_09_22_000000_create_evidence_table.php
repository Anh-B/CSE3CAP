<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Evidence attached to a reflection. Each row is either an uploaded
     * file or a link (e.g. a GitHub PR, a Google Doc), never both.
     */
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reflection_id')->constrained()->onDelete('cascade');
            $table->string('type');                        // 'file' or 'link'
            $table->string('description')->nullable();     // optional label, e.g. "Sprint 3 PR"
            $table->string('link', 2048)->nullable();      // set when type = link
            $table->string('file_path')->nullable();       // set when type = file (where it's stored)
            $table->string('original_name')->nullable();   // the file's name when it was uploaded
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};
