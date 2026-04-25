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
        $installment = fake()->boolean(50);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(20),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'quantity' => fake()->numberBetween(1, 5),
            'status' => fake()->randomElement(['available', 'separated', 'sold']),
            'payment_plans' => $installment
                ? [
                    ['type' => 'full'],
                    [
                        'type' => 'installment',
                        'down_payment' => (float) fake()->numberBetween(500, 5000),
                        'installments' => fake()->numberBetween(2, 6),
                    ],
                ]
                : [
                    ['type' => 'full'],
                ],
            'category' => fake()->randomElement(['Electronics', 'Fashion', 'Footwear', 'Accessories', 'Home']),
            'item_condition' => fake()->randomElement(['new', 'used_like_new', 'used_good']),
            'shipping_to_agree' => false,
            'date_added' => fake()->date(),
            'images' => [
                ['path' => null, 'url' => fake()->imageUrl(800, 800)],
            ],
        ];
    }
}
