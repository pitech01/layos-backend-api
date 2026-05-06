<?php

namespace Database\Seeders;

use App\Models\CourseChannel;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $instructor = User::where('role', 'instructor')->first();
        if (!$instructor) return;

        CourseChannel::updateOrCreate(
            ['name' => 'general-discussion', 'course_id' => null],
            [
                'description' => 'Platform-wide discussion for all students and instructors',
                'type' => 'public',
                'created_by' => $instructor->id
            ]
        );
    }
}
