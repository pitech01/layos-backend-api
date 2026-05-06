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
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->float('bg_x')->default(0);
            $table->float('bg_y')->default(0);
            $table->float('bg_width')->default(100);
            $table->float('bg_height')->default(100);
            $table->string('bg_object_fit')->default('contain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['bg_x', 'bg_y', 'bg_width', 'bg_height', 'bg_object_fit']);
        });
    }
};
