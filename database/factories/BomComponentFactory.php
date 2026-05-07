<?php

namespace Database\Factories;

use App\Models\Assembly;
use App\Models\BomComponent;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomComponent>
 */
class BomComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assembly_id' => Assembly::factory(),
            'raw_material_id' => RawMaterial::factory(),
            'required_quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
