<?php

namespace Database\Factories;

use App\Models\Reflection;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reflection_id' => Reflection::factory(),
            'assessor_id'   => null,
            'score'         => fake()->numberBetween(1, 5),
            'feedback'      => fake()->sentence(),
        ];
    }
}
