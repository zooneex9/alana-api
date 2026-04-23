<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->safeEmail(),
            'buyer_phone' => fake()->e164PhoneNumber(),
            'buyer_address' => fake()->address(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'payment_method' => 'stripe',
            'order_date' => fake()->date(),
            'status' => fake()->randomElement(['pending', 'completed', 'cancelled']),
            'meta' => ['mode' => fake()->randomElement(['buy', 'separate'])],
        ];
    }
}
