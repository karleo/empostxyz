<?php

namespace App\Actions\Invoices;

use App\Actions\Shipments\SyncShipmentToEpgl;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Services\Epgl\EpglClient;
use Illuminate\Support\Facades\Auth;

class SubmitInvoiceBatchToEpgl
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
        $invoice->load('shipments');

        if ($invoice->shipments->isEmpty()) {
            $invoice->update([
                'sync_status' => Invoice::STATUS_FAILED,
                'sync_message' => 'Cannot submit: link at least one shipment to this invoice.',
                'last_synced_at' => now(),
            ]);

            return $invoice->refresh();
        }

        $invoice->update([
            'sync_status' => Invoice::STATUS_PENDING,
            'sync_message' => 'Submitting shipments and invoice to EPGL…',
            'sync_errors' => null,
        ]);

        $success = 0;
        $failed = 0;
        $errors = [];

        $shipmentSync = new SyncShipmentToEpgl($this->client);

        foreach ($invoice->shipments as $shipment) {
            if ($shipment->sync_status === Shipment::STATUS_SUCCESS) {
                $invoiceResult = $this->issueInvoiceForShipment($invoice, $shipment);
                if ($invoiceResult) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = [
                        'trackingNumber' => $shipment->tracking_number,
                        'message' => $shipment->fresh()->sync_message,
                    ];
                }

                continue;
            }

            $shipment = $shipmentSync->handle($shipment);

            if ($shipment->sync_status !== Shipment::STATUS_SUCCESS) {
                $failed++;
                $errors[] = [
                    'trackingNumber' => $shipment->tracking_number,
                    'stage' => 'shipment',
                    'message' => $shipment->sync_message,
                    'errors' => $shipment->sync_errors,
                ];

                continue;
            }

            $invoiceResult = $this->issueInvoiceForShipment($invoice, $shipment);
            if ($invoiceResult) {
                $success++;
            } else {
                $failed++;
                $errors[] = [
                    'trackingNumber' => $shipment->tracking_number,
                    'stage' => 'invoice',
                    'message' => $shipment->fresh()->sync_message,
                ];
            }
        }

        if ($failed === 0) {
            $invoice->update([
                'sync_status' => Invoice::STATUS_SUCCESS,
                'sync_message' => __(':count shipment(s) and invoice submitted successfully.', ['count' => $success]),
                'sync_errors' => null,
                'correlation_id' => $invoice->shipments()->value('correlation_id'),
                'last_synced_at' => now(),
            ]);
        } elseif ($success === 0) {
            $invoice->update([
                'sync_status' => Invoice::STATUS_FAILED,
                'sync_message' => 'All shipments failed to submit to EPGL.',
                'sync_errors' => $errors,
                'last_synced_at' => now(),
            ]);
        } else {
            $invoice->update([
                'sync_status' => Invoice::STATUS_PARTIAL,
                'sync_message' => __(':success succeeded, :failed failed.', ['success' => $success, 'failed' => $failed]),
                'sync_errors' => $errors,
                'last_synced_at' => now(),
            ]);
        }

        return $invoice->refresh()->load('shipments');
    }

    private function issueInvoiceForShipment(Invoice $invoice, Shipment $shipment): bool
    {
        $base = $invoice->payload;
        $details = is_array($shipment->payload['details'] ?? null) ? $shipment->payload['details'] : [];
        $weight = is_array($details['declaredWeight'] ?? null)
            ? $details['declaredWeight']
            : (is_array($details['weight'] ?? null) ? $details['weight'] : ['unit' => 'KG', 'value' => 0]);

        $payload = [
            'trackingNumber' => $shipment->tracking_number,
            'chargeableWeight' => [
                'unit' => $base['chargeableWeight']['unit'] ?? ($weight['unit'] ?? 'KG'),
                'value' => $base['chargeableWeight']['value'] ?? ($weight['value'] ?? 0),
            ],
            'charges' => $base['charges'] ?? [],
            'invoice' => $base['invoice'] ?? [],
        ];

        if (! isset($base['chargeableWeight']['value']) || (float) $base['chargeableWeight']['value'] <= 0) {
            $payload['chargeableWeight'] = [
                'unit' => $weight['unit'] ?? 'KG',
                'value' => (float) ($weight['value'] ?? 0),
            ];
        }

        $client = $this->client->withAuditContext([
            'user_id' => Auth::id() ?? $invoice->user_id,
            'shipment_id' => $shipment->id,
            'invoice_id' => $invoice->id,
            'tracking_number' => $shipment->tracking_number,
        ]);

        $response = $client->issueInvoice($payload);

        if ($response->successful) {
            return true;
        }

        $shipment->update([
            'sync_status' => Shipment::STATUS_FAILED,
            'sync_message' => 'Shipment created but invoice failed: '.($response->message ?? 'Unknown error'),
            'sync_errors' => $response->errors,
            'correlation_id' => $response->correlationId ?? $shipment->correlation_id,
            'last_synced_at' => now(),
        ]);

        return false;
    }
}
