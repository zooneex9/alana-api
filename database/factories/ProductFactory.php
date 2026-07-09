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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $daily = fake()->randomFloat(2, 500, 5000);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(20),
            'price' => $daily,
            'rental_price_daily' => $daily,
            'rental_price_weekend' => fake()->optional()->randomFloat(2, 800, 8000),
            'deposit' => fake()->optional()->randomFloat(2, 500, 3000),
            'rental_duration_days' => fake()->randomElement([2, 3, 4, 5]),
            'quantity' => 1,
            'status' => fake()->randomElement(['available', 'reserved', 'rented']),
            'payment_plans' => [['type' => 'full']],
            'category' => fake()->randomElement(['Gala', 'Civil', 'XV Años', 'Cocktail']),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', '8', '10']),
            'color' => fake()->safeColorName(),
            'item_condition' => 'new',
            'shipping_to_agree' => false,
            'date_added' => fake()->date(),
            'images' => [
                ['path' => null, 'url' => fake()->imageUrl(800, 1200)],
            ],
        ];
    }
}
