<?php

namespace Database\Factories;

use App\Models\brands;
use App\Models\Categories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Products>
 */
class ProductsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categories_id' => Categories::inRandomOrder()->value('id'),
            'brands_id' => brands::inRandomOrder()->value('id'),

            'name' => fake()->unique()->words(3, true),
            'image' => fake()->optional()->imageUrl(640, 480, 'products'),

            'description' => fake()->sentence(12),
            'slug' => Str::slug(fake()->unique()->words(3, true)),

            'sku' => fake()->boolean(80)
                ? fake()->unique()->bothify('SKU-#####')
                : null,


            'tax' => fake()->randomFloat(2, 0, 20),
            'is_active' => fake()->boolean(90),

            'price' => fake()->randomFloat(2, 10, 5000),
            'is_featured' => fake()->boolean(20),

            'meta_title' => fake()->optional()->sentence(4),
            'meta_description' => fake()->optional()->sentence(8),

            'return_policy' => fake()->randomElement([
                '4 days',
                '7 days',
                '14 days',
                'No return'
            ]),
        ];
    }
}