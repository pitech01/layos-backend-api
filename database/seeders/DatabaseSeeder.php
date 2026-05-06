<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Demo Student',
            'email' => 'student@layos.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
        ]);

        User::factory()->create([
            'name' => 'Dr. Jane Smith',
            'email' => 'instructor@layos.com',
            'password' => bcrypt('password123'),
            'role' => 'instructor',
        ]);

        User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@dibia.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }
}
