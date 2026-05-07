<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    public function definition(): array
    {
        $reorderPoint = fake()->numberBetween(10, 50);

        return [
            'sku' => strtoupper(fake()->bothify('RM-####')),
            'name' => fake()->words(3, true),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'committed_quantity' => 0,
            'reorder_point' => $reorderPoint,
            'reorder_up_to_quantity' => $reorderPoint + fake()->numberBetween(20, 80),
        ];
    }
}
