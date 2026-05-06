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
            $table->dropColumn(['pricing', 'payment_model', 'payment_link']);
        });

        Schema::table('cohort_student', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'receipt_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->decimal('pricing', 10, 2)->nullable();
            $table->string('payment_model')->default('full');
            $table->string('payment_link')->nullable();
        });

        Schema::table('cohort_student', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid');
            $table->string('receipt_path')->nullable();
        });
    }
};
