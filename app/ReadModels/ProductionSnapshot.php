<?php

namespace App\ReadModels;

use App\Models\RawMaterial;

/**
 * Production view of a material's inventory position.
 * Uses available quantity (stock minus committed) — never raw physical stock.
 * See known asymmetry with ProcurementSnapshot / reorder alert logic.
 */
final class ProductionSnapshot
{
    private function __construct(
        public readonly int $materialId,
        private readonly int $availableQuantity,
    ) {}

    public static function from(RawMaterial $material): self
    {
        return new self(
            materialId: $material->id,
            availableQuantity: $material->stock_quantity - $material->committed_quantity,
        );
    }

    public function canFulfill(int $requiredQuantity): bool
    {
        return $this->availableQuantity >= $requiredQuantity;
    }
}
