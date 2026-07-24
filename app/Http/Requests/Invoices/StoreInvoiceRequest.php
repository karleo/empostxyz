<?php

namespace App\Http\Requests\Invoices;

use App\Models\Shipment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
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
            'chargeableWeight.unit' => ['required', 'string', 'in:KG,LB'],
            'chargeableWeight.value' => ['nullable', 'numeric', 'min:0'],
            'charges' => ['required', 'array', 'min:1'],
            'charges.*.type' => ['required', 'string', 'max:100'],
            'charges.*.amount.currencyCode' => ['required', 'string', 'size:3'],
            'charges.*.amount.amount' => ['required', 'numeric', 'min:0'],
            'invoice.invoiceNumber' => ['required', 'string', 'max:100'],
            'invoice.invoiceDate' => ['required', 'date'],
            'invoice.billingAccountNumber' => ['required', 'string', 'max:100'],
            'invoice.billingAccountName' => ['required', 'string', 'max:255'],
            'invoice.totalDiscountAmount' => ['required', 'numeric', 'min:0'],
            'invoice.taxAmount' => ['required', 'numeric', 'min:0'],
            'invoice.totalAmountIncludingTax' => ['required', 'numeric', 'min:0'],
            'invoice.currencyCode' => ['required', 'string', 'size:3'],
            'submit_now' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = $this->input('shipment_ids', []);
            if (! is_array($ids) || $ids === []) {
                return;
            }

            $blocked = Shipment::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('id', $ids)
                ->where(function ($q): void {
                    $q->whereNotNull('invoice_id')
                        ->orWhere(function ($inner): void {
                            $inner->whereNull('invoice_id')
                                ->whereNotIn('sync_status', [Shipment::STATUS_DRAFT, Shipment::STATUS_FAILED]);
                        });
                })
                ->pluck('tracking_number');

            if ($blocked->isNotEmpty()) {
                $validator->errors()->add(
                    'shipment_ids',
                    'These shipments are not available to link: '.$blocked->implode(', ')
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        unset($data['shipment_ids'], $data['submit_now']);

        return $data;
    }

    /**
     * @return list<int>
     */
    public function shipmentIds(): array
    {
        return array_map('intval', $this->validated('shipment_ids'));
    }

    public function shouldSubmit(): bool
    {
        return (bool) $this->boolean('submit_now');
    }
}
