<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceiveShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Shipment $shipment */
            $shipment = $this->route('shipment');

            if ($shipment->isAlreadyReceived()) {
                $validator->errors()->add('shipment', 'This shipment has already been received.');
            }
        });
    }
}
