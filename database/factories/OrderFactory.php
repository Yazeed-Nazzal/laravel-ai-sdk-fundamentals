<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /** @return array{user_id: mixed, status: string} */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered'])];
    }
}
