<?php

namespace App\Models;

use Database\Factories\AssemblyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'is_buildable'])]
class Assembly extends Model
{
    /** @use HasFactory<AssemblyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_buildable' => 'boolean',
        ];
    }

    public function bomComponents(): HasMany
    {
        return $this->hasMany(BomComponent::class);
    }
}
