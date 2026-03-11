<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->name().' Bot',
            'description' => fake()->sentence(),
            'api_token' => Agent::generateToken(),
        ];
    }
}
