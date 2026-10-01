<?php

namespace Database\Factories;

use App\Models\Reflection;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reflection_id' => Reflection::factory(),
            'type'          => 'link',
            'description'   => fake()->sentence(4),
            'link'          => fake()->url(),
        ];
    }
}
