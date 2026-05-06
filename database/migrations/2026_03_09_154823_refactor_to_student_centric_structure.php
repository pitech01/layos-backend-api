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
        Schema::rename('users', 'students');
        Schema::rename('cohort_user', 'cohort_student');

        Schema::table('cohort_student', function (Blueprint $table) {
            $table->renameColumn('user_id', 'student_id');
        });

        // For consistency, update lesson_user to lesson_student if it exists
        if (Schema::hasTable('lesson_user')) {
            Schema::rename('lesson_user', 'lesson_student');
            Schema::table('lesson_student', function (Blueprint $table) {
                $table->renameColumn('user_id', 'student_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lesson_student')) {
            Schema::table('lesson_student', function (Blueprint $table) {
                $table->renameColumn('student_id', 'user_id');
            });
            Schema::rename('lesson_student', 'lesson_user');
        }

        Schema::table('cohort_student', function (Blueprint $table) {
            $table->renameColumn('student_id', 'user_id');
        });

        Schema::rename('cohort_student', 'cohort_user');
        Schema::rename('students', 'users');
    }
};
