<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'fullname' => 'Admin Manager',
                'password' => \Illuminate\Support\Facades\Hash::make('123456'),
                'role' => 'manager'
            ]
        );
    }
}
