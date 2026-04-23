<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(20),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'status' => fake()->randomElement(['available', 'separated', 'sold']),
            'payment_type' => fake()->randomElement(['full', 'installment']),
            'down_payment' => fake()->optional()->randomFloat(2, 500, 10000),
            'installments' => fake()->optional()->numberBetween(2, 6),
            'category' => fake()->randomElement(['Electronics', 'Fashion', 'Footwear', 'Accessories', 'Home']),
            'date_added' => fake()->date(),
            'image_url' => fake()->imageUrl(800, 800),
        ];
    }
}
