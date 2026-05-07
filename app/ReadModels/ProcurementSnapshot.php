<?php

namespace App\ReadModels;

use App\Models\RawMaterial;

/**
 * Finance/procurement view of a material's inventory position after a shipment is received.
 * Uses physical stock only — committed quantity is intentionally excluded.
 * See known asymmetry with ProductionSnapshot / BOM buildability check.
 */
final class ProcurementSnapshot
{
    private function __construct(
        public readonly int $materialId,
        private readonly int $physicalStock,
        private readonly int $previousStock,
        private readonly int $reorderPoint,
        private readonly int $reorderUpToQuantity,
    ) {}

    public static function afterReceiving(RawMaterial $material, int $previousStock): self
    {
        return new self(
            materialId: $material->id,
            physicalStock: $material->stock_quantity,
            previousStock: $previousStock,
            reorderPoint: $material->reorder_point,
            reorderUpToQuantity: $material->reorder_up_to_quantity,
        );
    }

    /**
     * True when this shipment carried stock from below reorder_point to >= reorder_up_to_quantity.
     * Finance reconciles POs against this signal — do not gate on committed/available quantity.
     */
    public function crossedReorderThreshold(): bool
    {
        return $this->physicalStock >= $this->reorderUpToQuantity
            && $this->previousStock < $this->reorderPoint;
    }
}
