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
        if (!Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('certificate_uuid')->unique();
                $blueprint->foreignId('user_id')->nullable()->constrained('students')->onDelete('set null');
                $blueprint->foreignId('course_id')->constrained()->cascadeOnDelete();
                $blueprint->string('full_name');
                $blueprint->string('course_title');
                $blueprint->string('qr_code_path')->nullable();
                $blueprint->string('certificate_path')->nullable();
                $blueprint->string('issued_by')->nullable();
                $blueprint->timestamp('issued_at');
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
