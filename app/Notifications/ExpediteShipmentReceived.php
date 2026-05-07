<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExpediteShipmentReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['log'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shipment_id' => $this->shipment->id,
            'manifest_reference' => $this->shipment->manifest_reference,
            'message' => "Expedited shipment {$this->shipment->manifest_reference} has been received.",
        ];
    }
}
