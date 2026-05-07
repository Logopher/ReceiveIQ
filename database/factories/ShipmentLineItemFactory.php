<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\Shipment;
use App\Models\ShipmentLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentLineItem>
 */
class ShipmentLineItemFactory extends Factory
{
    public function definition(): array
    {
        $expected = fake()->numberBetween(10, 200);

        return [
            'shipment_id' => Shipment::factory(),
            'raw_material_id' => RawMaterial::factory(),
            'expected_quantity' => $expected,
            'actual_quantity' => $expected,
        ];
    }
}
