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
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn(['delivery_mode', 'seat_limit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('delivery_mode')->default('recorded');
            $table->integer('seat_limit')->default(100);
        });
    }
};
