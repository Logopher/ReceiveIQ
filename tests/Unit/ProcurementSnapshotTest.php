<?php

use App\Models\RawMaterial;
use App\ReadModels\ProcurementSnapshot;

function makeProcurementMaterial(int $stock, int $reorderPoint, int $reorderUpTo): RawMaterial
{
    $material = new RawMaterial;
    $material->id = 1;
    $material->stock_quantity = $stock;
    $material->committed_quantity = 0;
    $material->reorder_point = $reorderPoint;
    $material->reorder_up_to_quantity = $reorderUpTo;

    return $material;
}

test('crossedReorderThreshold is true when stock moved from below reorder_point to at or above reorder_up_to_quantity', function () {
    $material = makeProcurementMaterial(stock: 100, reorderPoint: 50, reorderUpTo: 100);
    $snapshot = ProcurementSnapshot::afterReceiving($material, previousStock: 30);

    expect($snapshot->crossedReorderThreshold())->toBeTrue();
});

test('crossedReorderThreshold is false when previous stock was already above reorder_point', function () {
    $material = makeProcurementMaterial(stock: 100, reorderPoint: 50, reorderUpTo: 100);
    $snapshot = ProcurementSnapshot::afterReceiving($material, previousStock: 60);

    expect($snapshot->crossedReorderThreshold())->toBeFalse();
});

test('crossedReorderThreshold is false when current stock has not reached reorder_up_to_quantity', function () {
    $material = makeProcurementMaterial(stock: 80, reorderPoint: 50, reorderUpTo: 100);
    $snapshot = ProcurementSnapshot::afterReceiving($material, previousStock: 30);

    expect($snapshot->crossedReorderThreshold())->toBeFalse();
});

test('crossedReorderThreshold is false when neither condition is met', function () {
    $material = makeProcurementMaterial(stock: 80, reorderPoint: 50, reorderUpTo: 100);
    $snapshot = ProcurementSnapshot::afterReceiving($material, previousStock: 60);

    expect($snapshot->crossedReorderThreshold())->toBeFalse();
});
