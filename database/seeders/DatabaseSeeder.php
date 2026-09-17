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
    User::updateOrCreate(
        ['email' => 'ahseven@trainer.com'], // Your personal master email
        [
            'name' => 'Ah Seven',
            'password' => bcrypt('admin123'),
            'role' => 'super_admin',
        ]
    );
}
}
