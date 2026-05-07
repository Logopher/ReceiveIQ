<?php

use App\Models\Supplier;

function makeSupplier(?float $shrinkagePct): Supplier
{
    $supplier = new Supplier;
    $supplier->shrinkage_allowance_percentage = $shrinkagePct;

    return $supplier;
}

test('applyShrinkage returns full quantity when percentage is null', function () {
    expect(makeSupplier(null)->applyShrinkage(1000))->toBe(1000);
});

test('applyShrinkage returns full quantity when percentage is zero', function () {
    expect(makeSupplier(0.0)->applyShrinkage(1000))->toBe(1000);
});

test('applyShrinkage deducts the contractual percentage from the scanned quantity', function () {
    // 1000 units at 3% shrinkage → 30 deducted → 970 accepted
    expect(makeSupplier(3.0)->applyShrinkage(1000))->toBe(970);
});

test('applyShrinkage floors fractional results', function () {
    // 10 units at 3% shrinkage → 0.3 deducted → floor(9.7) = 9
    expect(makeSupplier(3.0)->applyShrinkage(10))->toBe(9);
});
