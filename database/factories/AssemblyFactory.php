<?php

namespace Database\Factories;

use App\Models\Assembly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assembly>
 */
class AssemblyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->bothify('KIT-####')),
            'name' => fake()->words(3, true),
            'is_buildable' => false,
        ];
    }
}
