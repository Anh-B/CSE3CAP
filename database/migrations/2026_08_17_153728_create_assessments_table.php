<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reflection_id')->constrained()->onDelete('cascade'); // Liên kết tới bài tự đánh giá
            $table->foreignId('assessor_id')->nullable(); // ID của Người chấm
            $table->integer('score'); // Điểm Assessor chấm (1 - 5)
            $table->text('feedback')->nullable(); // Lời nhắn / Phản hồi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
