<?php

namespace App\Http\Requests\Invoices;

use App\Models\Shipment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LinkShipmentsRequest extends FormRequest
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
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => [
                'integer',
                Rule::exists('shipments', 'id')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('shipment_ids', []);
            if (! is_array($ids)) {
                return;
            }

            $blocked = Shipment::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('id', $ids)
                ->whereNotNull('invoice_id')
                ->pluck('tracking_number');

            if ($blocked->isNotEmpty()) {
                $validator->errors()->add(
                    'shipment_ids',
                    'Already linked: '.$blocked->implode(', ')
                );
            }
        });
    }

    /**
     * @return list<int>
     */
    public function shipmentIds(): array
    {
        return array_map('intval', $this->validated('shipment_ids'));
    }
}
