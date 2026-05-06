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
            $table->string('template_path')->nullable()->change();
            $table->float('name_x')->nullable()->change();
            $table->float('name_y')->nullable()->change();
            $table->float('course_x')->nullable()->change();
            $table->float('course_y')->nullable()->change();
            $table->float('date_x')->nullable()->change();
            $table->float('date_y')->nullable()->change();
            $table->float('cert_id_x')->nullable()->change();
            $table->float('cert_id_y')->nullable()->change();
            $table->string('font_color')->nullable()->change();
            $table->integer('font_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('template_path')->nullable(false)->change();
            $table->float('name_x')->nullable(false)->change();
            $table->float('name_y')->nullable(false)->change();
            $table->float('course_x')->nullable(false)->change();
            $table->float('course_y')->nullable(false)->change();
            $table->float('date_x')->nullable(false)->change();
            $table->float('date_y')->nullable(false)->change();
            $table->float('cert_id_x')->nullable(false)->change();
            $table->float('cert_id_y')->nullable(false)->change();
            $table->string('font_color')->nullable(false)->change();
            $table->integer('font_size')->nullable(false)->change();
        });
    }
};
