<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Force the tables to use utf8mb4 collation to support Emojis
        DB::statement('ALTER TABLE channel_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::statement('ALTER TABLE direct_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fallback to standard utf8 if rolled back
        DB::statement('ALTER TABLE channel_messages CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        DB::statement('ALTER TABLE direct_messages CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
    }
};
