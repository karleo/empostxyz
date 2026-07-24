<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trackingNumber' => ['required', 'string', 'max:100'],
            'uhawb' => ['nullable', 'string', 'max:100'],
            'sender.name' => ['required', 'string', 'max:255'],
            'sender.email' => ['nullable', 'email', 'max:255'],
            'sender.phone' => ['required', 'string', 'max:50'],
            'sender.secondPhone' => ['nullable', 'string', 'max:50'],
            'sender.countryCode' => ['required', 'string', 'size:2'],
            'sender.state' => ['nullable', 'string', 'max:100'],
            'sender.postCode' => ['nullable', 'string', 'max:30'],
            'sender.city' => ['required', 'string', 'max:100'],
            'sender.line1' => ['required', 'string', 'max:255'],
            'sender.line2' => ['nullable', 'string', 'max:255'],
            'sender.line3' => ['nullable', 'string', 'max:255'],
            'receiver.name' => ['required', 'string', 'max:255'],
            'receiver.email' => ['nullable', 'email', 'max:255'],
            'receiver.phone' => ['required', 'string', 'max:50'],
            'receiver.secondPhone' => ['nullable', 'string', 'max:50'],
            'receiver.countryCode' => ['required', 'string', 'size:2'],
            'receiver.state' => ['nullable', 'string', 'max:100'],
            'receiver.postCode' => ['nullable', 'string', 'max:30'],
            'receiver.city' => ['required', 'string', 'max:100'],
            'receiver.line1' => ['required', 'string', 'max:255'],
            'receiver.line2' => ['nullable', 'string', 'max:255'],
            'receiver.line3' => ['nullable', 'string', 'max:255'],
            'details.weight.unit' => ['required', 'string', 'in:KG,LB'],
            'details.weight.value' => ['required', 'numeric', 'min:0'],
            'details.declaredWeight.unit' => ['required', 'string', 'in:KG,LB'],
            'details.declaredWeight.value' => ['required', 'numeric', 'min:0'],
            'details.cod.currencyCode' => ['nullable', 'string', 'size:3'],
            'details.cod.amount' => ['nullable', 'numeric', 'min:0'],
            'details.customs.currencyCode' => ['nullable', 'string', 'size:3'],
            'details.customs.amount' => ['nullable', 'numeric', 'min:0'],
            'details.deliveryCharges.currencyCode' => ['nullable', 'string', 'size:3'],
            'details.deliveryCharges.amount' => ['nullable', 'numeric', 'min:0'],
            'details.numberOfPieces' => ['required', 'integer', 'min:1'],
            'details.pickupDate' => ['required', 'date'],
            'details.deliveryStatus' => ['nullable', 'string', 'max:100'],
            'details.deliveryDate' => ['nullable', 'date'],
            'details.deliveryAttempts' => ['nullable', 'integer', 'min:0'],
            'details.shippingType' => ['required', 'string', 'in:INT,DOM'],
            'details.productCategory' => ['nullable', 'string', 'max:100'],
            'details.productType' => ['nullable', 'string', 'max:100'],
            'details.descriptionOfGoods' => ['nullable', 'string', 'max:500'],
            'details.dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'details.dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'details.dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'details.dimensions.unit' => ['nullable', 'string', 'in:CM,IN'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.countryOfOrigin' => ['nullable', 'string', 'size:2'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.hsCode' => ['nullable', 'string', 'max:20'],
            'items.*.customsValue.currencyCode' => ['nullable', 'string', 'size:3'],
            'items.*.customsValue.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight.unit' => ['required', 'string', 'in:KG,LB'],
            'items.*.weight.value' => ['required', 'numeric', 'min:0'],
            'items.*.dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'items.*.dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'items.*.dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'items.*.dimensions.unit' => ['nullable', 'string', 'in:CM,IN'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (empty($data['uhawb'])) {
            $data['uhawb'] = null;
        }

        return $data;
    }
}
