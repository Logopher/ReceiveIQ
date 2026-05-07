<?php

namespace App\Models;

use Database\Factories\BomComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assembly_id', 'raw_material_id', 'required_quantity'])]
class BomComponent extends Model
{
    /** @use HasFactory<BomComponentFactory> */
    use HasFactory;

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
