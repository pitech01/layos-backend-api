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
        Schema::table('channel_messages', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('course_id')->constrained('course_channels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
        });
    }
};
