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
        Schema::rename('assessments', 'assignments');
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('assignment_file')->nullable()->after('cohort_id');
        });

        Schema::rename('assessment_submissions', 'assignment_submissions');
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->renameColumn('assessment_id', 'assignment_id');
            $table->renameColumn('file_upload', 'submission_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->renameColumn('assignment_id', 'assessment_id');
            $table->renameColumn('submission_file', 'file_upload');
        });
        Schema::rename('assignment_submissions', 'assessment_submissions');

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('assignment_file');
        });
        Schema::rename('assignments', 'assessments');
    }
};
