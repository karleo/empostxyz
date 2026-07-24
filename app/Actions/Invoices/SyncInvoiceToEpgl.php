<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Services\Epgl\EpglClient;

class SyncInvoiceToEpgl
{
    public function __construct(
        private readonly EpglClient $client,
    ) {}

    public static function make(): self
    {
        return new self(EpglClient::make());
    }

    public function handle(Invoice $invoice): Invoice
    {
        $invoice->update([
            'sync_status' => Invoice::STATUS_PENDING,
            'sync_message' => 'Sending to EPGL…',
            'sync_errors' => null,
        ]);

        $response = $this->client->issueInvoice($invoice->payload);

        if ($response->successful) {
            $invoice->update([
                'sync_status' => Invoice::STATUS_SUCCESS,
                'sync_message' => $response->message ?? 'Shipment invoice issued successfully.',
                'sync_errors' => null,
                'correlation_id' => $response->correlationId,
                'last_synced_at' => now(),
            ]);
        } else {
            $invoice->update([
                'sync_status' => Invoice::STATUS_FAILED,
                'sync_message' => $response->message ?? 'Failed to issue invoice.',
                'sync_errors' => $response->errors,
                'correlation_id' => $response->correlationId,
                'last_synced_at' => now(),
            ]);
        }

        return $invoice->refresh();
    }
}
