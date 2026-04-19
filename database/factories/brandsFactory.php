<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\brands;

class brandsFactory extends Factory
{
    protected $model = brands::class;

    public function definition()
    {
        $name = $this->faker->unique()->company;

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . time(),
            'image' => null,
        ];
    }
}
