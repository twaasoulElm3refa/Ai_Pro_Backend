<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\brands;
use App\Models\Categories;

class BrandCategorySeeder extends Seeder
{
    public function run()
    {
        Categories::factory(20)->create();
        brands::factory(20)->create();
    }
}
