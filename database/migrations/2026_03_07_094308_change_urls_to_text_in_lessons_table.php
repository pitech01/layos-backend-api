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
        Schema::table('lessons', function (Blueprint $table) {
            $table->text('video_url')->nullable()->change();
            $table->text('file_url')->nullable()->change();
            $table->text('live_link')->nullable()->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->text('thumbnail')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_url')->nullable()->change();
            $table->string('file_url')->nullable()->change();
            $table->string('live_link')->nullable()->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('thumbnail')->nullable()->change();
        });
    }
};
