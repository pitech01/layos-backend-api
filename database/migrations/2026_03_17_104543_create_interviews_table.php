<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Force a clean start
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('interviews');
        Schema::enableForeignKeyConstraints();

        // 2. Create the Table Structure FIRST without constraints
        Schema::create('interviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('document_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('cohort_id')->nullable();
            
            /** 
             * TRY CHANGE: If BigInteger fails, change this to ->unsignedInteger('created_by')
             * but most likely BigInteger is correct if your users table is standard Laravel.
             */
            $table->unsignedBigInteger('created_by'); 
            
            $table->timestamps();

            // Add basic indices for performance
            $table->index('cohort_id');
            $table->index('created_by');
        });

        // 3. Add Foreign Keys in a separate block
        Schema::table('interviews', function (Blueprint $table) {
            $table->foreign('cohort_id')->references('id')->on('cohorts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
