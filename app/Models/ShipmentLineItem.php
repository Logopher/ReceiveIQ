<?php

namespace App\Models;

use Database\Factories\ShipmentLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipment_id', 'raw_material_id', 'expected_quantity', 'actual_quantity'])]
class ShipmentLineItem extends Model
{
    /** @use HasFactory<ShipmentLineItemFactory> */
    use HasFactory;

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
