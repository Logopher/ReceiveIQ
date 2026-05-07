<?php

namespace App\Models;

use Database\Factories\RawMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'stock_quantity', 'committed_quantity', 'reorder_point', 'reorder_up_to_quantity'])]
class RawMaterial extends Model
{
    /** @use HasFactory<RawMaterialFactory> */
    use HasFactory;

    public function bomComponents(): HasMany
    {
        return $this->hasMany(BomComponent::class);
    }

    public function shipmentLineItems(): HasMany
    {
        return $this->hasMany(ShipmentLineItem::class);
    }
}
