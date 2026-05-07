<?php

use App\Models\RawMaterial;
use App\ReadModels\ProductionSnapshot;

function makeProductionMaterial(int $stock, int $committed): RawMaterial
{
    $material = new RawMaterial;
    $material->id = 1;
    $material->stock_quantity = $stock;
    $material->committed_quantity = $committed;
    $material->reorder_point = 0;
    $material->reorder_up_to_quantity = 0;

    return $material;
}

test('canFulfill returns true when available quantity meets required quantity exactly', function () {
    $material = makeProductionMaterial(stock: 100, committed: 60);
    $snapshot = ProductionSnapshot::from($material);

    expect($snapshot->canFulfill(40))->toBeTrue();
});

test('canFulfill returns true when available quantity exceeds required quantity', function () {
    $material = makeProductionMaterial(stock: 100, committed: 10);
    $snapshot = ProductionSnapshot::from($material);

    expect($snapshot->canFulfill(40))->toBeTrue();
});

test('canFulfill returns false when available quantity is insufficient', function () {
    $material = makeProductionMaterial(stock: 100, committed: 70);
    $snapshot = ProductionSnapshot::from($material);

    expect($snapshot->canFulfill(40))->toBeFalse();
});

test('canFulfill uses available quantity not physical stock', function () {
    // Physical stock (100) would satisfy requirement (80), but committed (30) reduces available to 70.
    $material = makeProductionMaterial(stock: 100, committed: 30);
    $snapshot = ProductionSnapshot::from($material);

    expect($snapshot->canFulfill(80))->toBeFalse();
});
