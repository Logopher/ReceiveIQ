<?php

namespace App\Events;

use App\Models\RawMaterial;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReorderAlertTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RawMaterial $rawMaterial) {}
}
