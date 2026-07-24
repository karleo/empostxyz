<?php

namespace App\Services\Shipments;

use InvalidArgumentException;

class CsvShipmentImporter
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'tracking_number',
            'uhawb',
            'sender_name',
            'sender_email',
            'sender_phone',
            'sender_second_phone',
            'sender_country_code',
            'sender_state',
            'sender_post_code',
            'sender_city',
            'sender_line1',
            'sender_line2',
            'sender_line3',
            'receiver_name',
            'receiver_email',
            'receiver_phone',
            'receiver_second_phone',
            'receiver_country_code',
            'receiver_state',
            'receiver_post_code',
            'receiver_city',
            'receiver_line1',
            'receiver_line2',
            'receiver_line3',
            'weight_unit',
            'weight_value',
            'declared_weight_unit',
            'declared_weight_value',
            'number_of_pieces',
            'pickup_date',
            'shipping_type',
            'product_category',
            'product_type',
            'description_of_goods',
            'length',
            'width',
            'height',
            'dimension_unit',
            'item_description',
            'item_country_of_origin',
            'item_quantity',
            'item_hs_code',
            'item_customs_currency',
            'item_customs_amount',
            'item_weight_unit',
            'item_weight_value',
        ];
    }

    /**
     * @return list<array{tracking_number: string, uhawb: string|null, payload: array<string, mixed>}>
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to open CSV file.');
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $required = ['tracking_number', 'sender_name', 'sender_phone', 'sender_country_code', 'sender_city', 'sender_line1', 'receiver_name', 'receiver_phone', 'receiver_country_code', 'receiver_city', 'receiver_line1', 'weight_unit', 'weight_value', 'item_description'];

        foreach ($required as $column) {
            if (! in_array($column, $headers, true)) {
                fclose($handle);
                throw new InvalidArgumentException("CSV is missing required column: {$column}");
            }
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            if ($row['tracking_number'] === '') {
                fclose($handle);
                throw new InvalidArgumentException("Row {$line}: tracking_number is required.");
            }

            $rows[] = [
                'tracking_number' => $row['tracking_number'],
                'uhawb' => ($row['uhawb'] ?? '') !== '' ? $row['uhawb'] : null,
                'payload' => $this->mapRow($row),
            ];
        }

        fclose($handle);

        if ($rows === []) {
            throw new InvalidArgumentException('CSV has no data rows.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $data
     */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $value = fn (string $key, mixed $default = '') => $row[$key] ?? $default;

        $payload = [
            'trackingNumber' => $value('tracking_number'),
            'uhawb' => $value('uhawb') !== '' ? $value('uhawb') : null,
            'sender' => [
                'name' => $value('sender_name'),
                'email' => $value('sender_email'),
                'phone' => $value('sender_phone'),
                'secondPhone' => $value('sender_second_phone'),
                'countryCode' => $value('sender_country_code'),
                'state' => $value('sender_state'),
                'postCode' => $value('sender_post_code'),
                'city' => $value('sender_city'),
                'line1' => $value('sender_line1'),
                'line2' => $value('sender_line2'),
                'line3' => $value('sender_line3'),
            ],
            'receiver' => [
                'name' => $value('receiver_name'),
                'email' => $value('receiver_email'),
                'phone' => $value('receiver_phone'),
                'secondPhone' => $value('receiver_second_phone'),
                'countryCode' => $value('receiver_country_code'),
                'state' => $value('receiver_state'),
                'postCode' => $value('receiver_post_code'),
                'city' => $value('receiver_city'),
                'line1' => $value('receiver_line1'),
                'line2' => $value('receiver_line2'),
                'line3' => $value('receiver_line3'),
            ],
            'details' => [
                'weight' => [
                    'unit' => $value('weight_unit', 'KG'),
                    'value' => (float) $value('weight_value', 0),
                ],
                'declaredWeight' => [
                    'unit' => $value('declared_weight_unit', $value('weight_unit', 'KG')),
                    'value' => (float) $value('declared_weight_value', $value('weight_value', 0)),
                ],
                'numberOfPieces' => (int) $value('number_of_pieces', 1),
                'pickupDate' => $value('pickup_date') !== '' ? $value('pickup_date') : now()->toIso8601String(),
                'shippingType' => $value('shipping_type', 'INT'),
                'productCategory' => $value('product_category'),
                'productType' => $value('product_type', 'Parcel'),
                'descriptionOfGoods' => $value('description_of_goods'),
                'dimensions' => [
                    'length' => (float) $value('length', 0),
                    'width' => (float) $value('width', 0),
                    'height' => (float) $value('height', 0),
                    'unit' => $value('dimension_unit', 'CM'),
                ],
            ],
            'items' => [
                [
                    'description' => $value('item_description'),
                    'countryOfOrigin' => $value('item_country_of_origin'),
                    'quantity' => (float) $value('item_quantity', 1),
                    'hsCode' => $value('item_hs_code'),
                    'customsValue' => [
                        'currencyCode' => $value('item_customs_currency', 'USD'),
                        'amount' => (float) $value('item_customs_amount', 0),
                    ],
                    'weight' => [
                        'unit' => $value('item_weight_unit', $value('weight_unit', 'KG')),
                        'value' => (float) $value('item_weight_value', $value('weight_value', 0)),
                    ],
                ],
            ],
        ];

        return $payload;
    }
}
