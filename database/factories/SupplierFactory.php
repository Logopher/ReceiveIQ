<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'on_time_delivery_score' => fake()->randomFloat(2, 60, 100),
            'shrinkage_allowance_percentage' => null,
            'uses_case_counts' => false,
        ];
    }

    public function bertolini(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bertolini',
            'uses_case_counts' => true,
        ]);
    }
}
