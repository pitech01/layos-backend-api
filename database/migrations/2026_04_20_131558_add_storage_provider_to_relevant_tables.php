<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'lessons',
            'assignments',
            'assignment_submissions',
            'channel_messages',
            'direct_messages',
            'interviews',
            'certificate_templates',
            'certificates',
            'courses'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->enum('storage_provider', ['aws', 'local', 'bunny'])->default('local')->after('id');
            });
        }

        // Data migration: Set existing AWS records
        // Using raw SQL for efficiency and to avoid model side-effects during migration
        DB::table('lessons')
            ->where('video_url', 'like', '%amazonaws.com%')
            ->orWhere('file_url', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('assignments')
            ->where('assignment_file', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('assignment_submissions')
            ->where('submission_file', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('channel_messages')
            ->where('attachment_url', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('direct_messages')
            ->where('attachment_url', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('interviews')
            ->where('document_path', 'like', '%amazonaws.com%')
            ->orWhere('video_path', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('certificate_templates')
            ->where('template_path', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('certificates')
            ->where('certificate_path', 'like', '%amazonaws.com%')
            ->orWhere('qr_code_path', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);

        DB::table('courses')
            ->where('thumbnail', 'like', '%amazonaws.com%')
            ->update(['storage_provider' => 'aws']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'lessons',
            'assignments',
            'assignment_submissions',
            'channel_messages',
            'direct_messages',
            'interviews',
            'certificate_templates',
            'certificates',
            'courses'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('storage_provider');
            });
        }
    }
};
