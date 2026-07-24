<?php

namespace App\Actions\Shipments;

use App\Models\Shipment;
use App\Services\Epgl\EpglClient;
use Illuminate\Support\Facades\Auth;

class SyncShipmentToEpgl
{
    public function __construct(
        private readonly EpglClient $client,
    ) {}

    public static function make(): self
    {
        return new self(EpglClient::make());
    }

    public function handle(Shipment $shipment): Shipment
    {
        if ($shipment->invoice_id === null) {
            $shipment->update([
                'sync_status' => Shipment::STATUS_DRAFT,
                'sync_message' => 'Cannot submit shipment without an invoice. Link it to an invoice first.',
                'sync_errors' => null,
            ]);

            return $shipment->refresh();
        }

        $shipment->update([
            'sync_status' => Shipment::STATUS_PENDING,
            'sync_message' => 'Sending shipment to EPGL…',
            'sync_errors' => null,
        ]);

        $client = $this->client->withAuditContext([
            'user_id' => Auth::id() ?? $shipment->user_id,
            'shipment_id' => $shipment->id,
            'invoice_id' => $shipment->invoice_id,
            'tracking_number' => $shipment->tracking_number,
        ]);

        $response = $client->createShipment($shipment->payload);

        if ($response->successful) {
            $uhawb = $shipment->uhawb;
            if (is_array($response->data) && isset($response->data['uhawb']) && is_string($response->data['uhawb'])) {
                $uhawb = $response->data['uhawb'];
            }

            $shipment->update([
                'uhawb' => $uhawb,
                'sync_status' => Shipment::STATUS_SUCCESS,
                'sync_message' => $response->message ?? 'Shipment created successfully.',
                'sync_errors' => null,
                'correlation_id' => $response->correlationId,
                'last_synced_at' => now(),
            ]);
        } else {
            $shipment->update([
                'sync_status' => Shipment::STATUS_FAILED,
                'sync_message' => $response->message ?? 'Failed to create shipment.',
                'sync_errors' => $response->errors,
                'correlation_id' => $response->correlationId,
                'last_synced_at' => now(),
            ]);
        }

        return $shipment->refresh();
    }
}
