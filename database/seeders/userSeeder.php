<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class userSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        User::create([
            'name' => 'Ai Pro_Admin',
            'role' => 'admin',
            'email' => 'admin@admin.com',
            'is_active' => 1,
            'is_verified' => 1,
            'password' => Hash::make('Ai_pro_2026')
        ]);
    }
}
