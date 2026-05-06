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
        Schema::create('certificate_templates', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('course_id')->constrained()->cascadeOnDelete();
            $blueprint->string('template_path');
            // Store positions as percentages to handle different sizes
            $blueprint->float('name_x');
            $blueprint->float('name_y');
            $blueprint->float('course_x');
            $blueprint->float('course_y');
            $blueprint->float('date_x');
            $blueprint->float('date_y');
            $blueprint->float('cert_id_x');
            $blueprint->float('cert_id_y');
            $blueprint->float('qr_x')->nullable();
            $blueprint->float('qr_y')->nullable();
            $blueprint->float('qr_size')->default(100);
            $blueprint->string('font_color')->default('#000000');
            $blueprint->integer('font_size')->default(24);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
