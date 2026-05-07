<?php

namespace App\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'manifest_reference', 'expected_ship_date', 'actual_ship_date', 'is_expedited', 'received_at'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_ship_date' => 'date',
            'actual_ship_date' => 'date',
            'is_expedited' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ShipmentLineItem::class);
    }

    public function isAlreadyReceived(): bool
    {
        return $this->received_at !== null;
    }

    public function wasOnTime(): bool
    {
        return $this->actual_ship_date !== null
            && $this->actual_ship_date->lte($this->expected_ship_date);
    }
}
