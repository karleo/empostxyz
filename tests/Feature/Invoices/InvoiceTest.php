<?php

use App\Models\ApiSetting;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function configureEpglForInvoices(): void
{
    ApiSetting::current()->update([
        'base_url' => 'https://api.epgl.ae',
        'client_id' => 'client-123',
        'client_secret' => 'secret-456',
    ]);
}

function makeDraftShipment(User $user, string $tracking): Shipment
{
    return Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => $tracking,
        'payload' => [
            'trackingNumber' => $tracking,
            'details' => [
                'declaredWeight' => ['unit' => 'KG', 'value' => 5.5],
                'weight' => ['unit' => 'KG', 'value' => 5.5],
            ],
            'sender' => ['name' => 'A', 'phone' => '+1', 'countryCode' => 'US', 'city' => 'X', 'line1' => 'Y'],
            'receiver' => ['name' => 'B', 'phone' => '+2', 'countryCode' => 'GB', 'city' => 'Z', 'line1' => 'W'],
            'items' => [['description' => 'Item', 'quantity' => 1, 'weight' => ['unit' => 'KG', 'value' => 1]]],
        ],
        'sync_status' => Shipment::STATUS_DRAFT,
        'sync_message' => 'Draft',
        'source' => 'csv',
    ]);
}

function invoiceForm(array $shipmentIds, bool $submit = false): array
{
    return [
        'shipment_ids' => $shipmentIds,
        'submit_now' => $submit,
        'chargeableWeight' => ['unit' => 'KG', 'value' => 0],
        'charges' => [
            [
                'type' => 'Base Rate',
                'amount' => ['currencyCode' => 'USD', 'amount' => 100],
            ],
        ],
        'invoice' => [
            'invoiceNumber' => 'INV-MULTI',
            'invoiceDate' => now()->toIso8601String(),
            'billingAccountNumber' => 'ACC-1',
            'billingAccountName' => 'Acme',
            'totalDiscountAmount' => 0,
            'taxAmount' => 5,
            'totalAmountIncludingTax' => 105,
            'currencyCode' => 'USD',
        ],
    ];
}

test('invoice can link multiple shipments as draft without submitting', function () {
    $user = User::factory()->create();
    configureEpglForInvoices();
    Http::fake();

    $a = makeDraftShipment($user, 'TRACK-A');
    $b = makeDraftShipment($user, 'TRACK-B');

    $response = $this->actingAs($user)
        ->post(route('invoices.store'), invoiceForm([$a->id, $b->id], submit: false));

    $invoice = Invoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->sync_status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->shipments()->count())->toBe(2)
        ->and($a->refresh()->invoice_id)->toBe($invoice->id)
        ->and($b->refresh()->invoice_id)->toBe($invoice->id);

    Http::assertNothingSent();
    $response->assertRedirect(route('invoices.show', $invoice));
});

test('submitting invoice sends all linked shipments then invoices to EPGL', function () {
    $user = User::factory()->create();
    configureEpglForInvoices();

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
            'correlationId' => 'c1',
        ], 201),
        'https://api.epgl.ae/api/v1/shipment/issueInvoice' => Http::response([
            'status' => 'Success',
            'message' => 'Shipment invoice issued successfully.',
            'correlationId' => 'c2',
        ], 201),
    ]);

    $a = makeDraftShipment($user, 'TRACK-A');
    $b = makeDraftShipment($user, 'TRACK-B');

    $response = $this->actingAs($user)
        ->post(route('invoices.store'), invoiceForm([$a->id, $b->id], submit: true));

    $invoice = Invoice::query()->first();

    expect($invoice->sync_status)->toBe(Invoice::STATUS_SUCCESS)
        ->and($a->refresh()->sync_status)->toBe(Shipment::STATUS_SUCCESS)
        ->and($b->refresh()->sync_status)->toBe(Shipment::STATUS_SUCCESS);

    Http::assertSentCount(5); // 1 auth + 2 create + 2 invoice
    $response->assertRedirect(route('invoices.show', $invoice));
});

test('more shipments can be linked to a draft invoice then submitted', function () {
    $user = User::factory()->create();
    configureEpglForInvoices();

    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'invoice_number' => 'INV-LINK',
        'payload' => invoiceForm([1]) /* placeholder */,
        'sync_status' => Invoice::STATUS_DRAFT,
    ]);

    // Fix payload to valid invoice payload shape
    $invoice->update([
        'payload' => [
            'chargeableWeight' => ['unit' => 'KG', 'value' => 0],
            'charges' => [
                ['type' => 'Base Rate', 'amount' => ['currencyCode' => 'USD', 'amount' => 10]],
            ],
            'invoice' => [
                'invoiceNumber' => 'INV-LINK',
                'invoiceDate' => now()->toIso8601String(),
                'billingAccountNumber' => 'ACC',
                'billingAccountName' => 'Name',
                'totalDiscountAmount' => 0,
                'taxAmount' => 0,
                'totalAmountIncludingTax' => 10,
                'currencyCode' => 'USD',
            ],
        ],
    ]);

    $a = makeDraftShipment($user, 'TRACK-L1');
    $b = makeDraftShipment($user, 'TRACK-L2');

    $this->actingAs($user)
        ->post(route('invoices.link', $invoice), [
            'shipment_ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->shipments()->count())->toBe(2);

    Http::fake([
        'https://api.epgl.ae/api/v1/auth/authenticate' => Http::response([
            'accessToken' => 'token-abc',
            'tokenType' => 'bearer',
            'expiresIn' => 3600,
        ], 200),
        'https://api.epgl.ae/api/v1/shipment/create' => Http::response([
            'status' => 'Success',
            'message' => 'ok',
            'data' => ['uhawb' => 'AE'],
            'correlationId' => 'c',
        ], 201),
        'https://api.epgl.ae/api/v1/shipment/issueInvoice' => Http::response([
            'status' => 'Success',
            'message' => 'ok',
            'correlationId' => 'c',
        ], 201),
    ]);

    $this->actingAs($user)
        ->post(route('invoices.submit', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->refresh()->sync_status)->toBe(Invoice::STATUS_SUCCESS);
});
