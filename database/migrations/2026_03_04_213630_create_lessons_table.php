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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->string('title');
            $table->string('type'); // video, live, material, quiz
            $table->string('duration')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_preview')->default(false);
            $table->string('video_url')->nullable();
            $table->string('video_source')->nullable();
            $table->date('live_date')->nullable();
            $table->time('live_time')->nullable();
            $table->string('live_platform')->nullable();
            $table->string('live_link')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_url')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
