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
        Schema::table('cohort_user', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status'); // unpaid, partial, pending_verification, full
            $table->string('receipt_path')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cohort_user', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'receipt_path']);
        });
    }
};
