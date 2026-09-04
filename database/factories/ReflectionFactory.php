<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReflectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'score'   => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
