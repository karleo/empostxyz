<?php

use App\Models\ApiSetting;
use App\Models\EpglAuditLog;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function configureEpglForAudit(): void
{
    ApiSetting::current()->update([
        'base_url' => 'https://api.epgl.ae',
        'client_id' => 'client-123',
        'client_secret' => 'secret-456',
    ]);
}

test('submitting to epgl writes audit logs with the submitting user', function () {
    $user = User::factory()->create(['name' => 'Audit User']);
    configureEpglForAudit();

    Http::fake([
        'https://api.epgl.ae/api/v1/auth/authenticate' => Http::response([
            'accessToken' => 'token-abc',
            'tokenType' => 'bearer',
            'expiresIn' => 3600,
        ], 200),
        'https://api.epgl.ae/api/v1/shipment/create' => Http::response([
            'status' => 'Success',
            'message' => 'Shipment created successfully.',
            'data' => ['uhawb' => 'AE1'],
            'correlationId' => 'corr-ship',
        ], 201),
        'https://api.epgl.ae/api/v1/shipment/issueInvoice' => Http::response([
            'status' => 'Success',
            'message' => 'Shipment invoice issued successfully.',
            'correlationId' => 'corr-inv',
        ], 201),
    ]);

    $shipment = Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-AUDIT-1',
        'payload' => [
            'trackingNumber' => 'TRACK-AUDIT-1',
            'details' => [
                'declaredWeight' => ['unit' => 'KG', 'value' => 2],
                'weight' => ['unit' => 'KG', 'value' => 2],
            ],
            'sender' => ['name' => 'A', 'phone' => '+1', 'countryCode' => 'US', 'city' => 'X', 'line1' => 'Y'],
            'receiver' => ['name' => 'B', 'phone' => '+2', 'countryCode' => 'GB', 'city' => 'Z', 'line1' => 'W'],
            'items' => [['description' => 'Item', 'quantity' => 1, 'weight' => ['unit' => 'KG', 'value' => 1]]],
        ],
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'invoice_number' => 'INV-AUDIT',
        'payload' => [
            'chargeableWeight' => ['unit' => 'KG', 'value' => 0],
            'charges' => [
                ['type' => 'Base Rate', 'amount' => ['currencyCode' => 'USD', 'amount' => 10]],
            ],
            'invoice' => [
                'invoiceNumber' => 'INV-AUDIT',
                'invoiceDate' => now()->toIso8601String(),
                'billingAccountNumber' => 'ACC',
                'billingAccountName' => 'Name',
                'totalDiscountAmount' => 0,
                'taxAmount' => 0,
                'totalAmountIncludingTax' => 10,
                'currencyCode' => 'USD',
            ],
        ],
        'sync_status' => Invoice::STATUS_DRAFT,
    ]);

    $shipment->update(['invoice_id' => $invoice->id]);

    $this->actingAs($user)
        ->post(route('invoices.submit', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    $logs = EpglAuditLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe(EpglAuditLog::ACTION_CREATE_SHIPMENT)
        ->and($logs[0]->user_id)->toBe($user->id)
        ->and($logs[0]->successful)->toBeTrue()
        ->and($logs[0]->tracking_number)->toBe('TRACK-AUDIT-1')
        ->and($logs[0]->request_payload)->toBeArray()
        ->and($logs[0]->response_body)->toHaveKey('correlationId')
        ->and($logs[1]->action)->toBe(EpglAuditLog::ACTION_ISSUE_INVOICE)
        ->and($logs[1]->user_id)->toBe($user->id)
        ->and($logs[1]->invoice_id)->toBe($invoice->id);
});

test('audit log history page shows who submitted', function () {
    $user = User::factory()->create(['name' => 'Viewer']);

    EpglAuditLog::query()->create([
        'user_id' => $user->id,
        'action' => EpglAuditLog::ACTION_CREATE_SHIPMENT,
        'endpoint' => 'https://api.epgl.ae/api/v1/shipment/create',
        'method' => 'POST',
        'tracking_number' => 'TRACK-X',
        'request_payload' => ['trackingNumber' => 'TRACK-X'],
        'response_body' => ['status' => 'Success'],
        'http_status' => 201,
        'successful' => true,
        'message' => 'Shipment created successfully.',
    ]);

    $this->actingAs($user)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('audit-logs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.user.name', 'Viewer')
        );
});
