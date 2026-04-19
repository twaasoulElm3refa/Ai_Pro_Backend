<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Categories;

class CategoriesFactory extends Factory
{
    protected $model = Categories::class;

    public function definition()
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . time(),
            'image' => null,
        ];
    }
}
