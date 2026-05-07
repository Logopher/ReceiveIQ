<?php

use App\Http\Controllers\ReceiveShipmentController;
use Illuminate\Support\Facades\Route;

Route::post('/shipments/{shipment}/receive', [ReceiveShipmentController::class, 'store'])
    ->name('shipments.receive');
