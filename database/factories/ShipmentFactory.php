<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $expectedDate = fake()->dateTimeBetween('-30 days', '-1 day');
        $actualDate = fake()->dateTimeBetween($expectedDate, 'now');

        return [
            'supplier_id' => Supplier::factory(),
            'manifest_reference' => strtoupper(fake()->bothify('MAN-####-??')),
            'expected_ship_date' => $expectedDate,
            'actual_ship_date' => $actualDate,
            'is_expedited' => false,
            'received_at' => null,
        ];
    }

    public function expedited(): static
    {
        return $this->state(fn (array $attributes) => ['is_expedited' => true]);
    }

    public function onTime(): static
    {
        $date = now()->subDay()->toDateString();

        return $this->state(fn (array $attributes) => [
            'expected_ship_date' => $date,
            'actual_ship_date' => $date,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_ship_date' => now()->subDays(3)->toDateString(),
            'actual_ship_date' => now()->subDay()->toDateString(),
        ]);
    }
}
