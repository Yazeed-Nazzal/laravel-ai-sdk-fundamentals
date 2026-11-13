<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnicalSupportIssueFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'message' => fake()->sentence(), 'category' => 'technical_issue', 'reported_order_id' => null, 'priority' => 'low', 'requires_human' => true, 'status' => 'open', 'priority_escalated_at' => now()];
    }
}
