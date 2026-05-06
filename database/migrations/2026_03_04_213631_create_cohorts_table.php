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
        Schema::create('cohorts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('enrollment_deadline');
            $table->string('timezone')->default('UTC+1 (WAT)');
            $table->string('delivery_mode')->default('recorded');
            $table->integer('seat_limit')->default(100);
            $table->decimal('pricing', 10, 2);
            $table->string('payment_model')->default('full');
            $table->string('visibility')->default('public');
            $table->foreignId('instructor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
