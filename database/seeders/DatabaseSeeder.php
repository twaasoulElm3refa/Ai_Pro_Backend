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
        $this->call([
            userSeeder::class,
            MainToolSeeder::class,
            SubToolSeeder::class,
            ProductDescriptionGeneratorSeeder::class,
            PromptGeneratorSeeder::class,
            PromptEnhancerSeeder::class,
            IdeaGeneratorSeeder::class,
        ]);
    }
}
