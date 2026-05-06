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
            $table->boolean('is_deleted')->default(false)->after('attachment_name');
        });
        
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('attachment_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_messages', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
        
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }
};
