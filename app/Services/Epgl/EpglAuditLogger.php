<?php

namespace App\Services\Epgl;

use App\Models\EpglAuditLog;
use Illuminate\Support\Facades\Auth;

class EpglAuditLogger
{
    /**
     * @param  array{
     *     action: string,
     *     endpoint: string,
     *     method?: string,
     *     request_payload?: array<string, mixed>|null,
     *     response_body?: array<string, mixed>|null,
     *     http_status?: int|null,
     *     successful: bool,
     *     correlation_id?: string|null,
     *     message?: string|null,
     *     tracking_number?: string|null,
     *     shipment_id?: int|null,
     *     invoice_id?: int|null,
     *     user_id?: int|null,
     * }  $data
     */
    public function log(array $data): EpglAuditLog
    {
        return EpglAuditLog::query()->create([
            'user_id' => $data['user_id'] ?? Auth::id(),
            'invoice_id' => $data['invoice_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'action' => $data['action'],
            'endpoint' => $data['endpoint'],
            'method' => $data['method'] ?? 'POST',
            'tracking_number' => $data['tracking_number'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'response_body' => $data['response_body'] ?? null,
            'http_status' => $data['http_status'] ?? null,
            'successful' => $data['successful'],
            'correlation_id' => $data['correlation_id'] ?? null,
            'message' => $data['message'] ?? null,
        ]);
    }
}
