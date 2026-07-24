<?php

use App\Models\ApiSetting;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function configureEpgl(): void
{
    ApiSetting::current()->update([
        'base_url' => 'https://api.epgl.ae',
        'client_id' => 'client-123',
        'client_secret' => 'secret-456',
    ]);
}

function shipmentPayload(string $tracking = 'TRACK123'): array
{
    return [
        'trackingNumber' => $tracking,
        'uhawb' => null,
        'sender' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+11234567890',
            'secondPhone' => '',
            'countryCode' => 'US',
            'state' => 'CA',
            'postCode' => '90210',
            'city' => 'Beverly Hills',
            'line1' => '1234 Elm Street',
            'line2' => '',
            'line3' => '',
        ],
        'receiver' => [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+442071234567',
            'secondPhone' => '',
            'countryCode' => 'GB',
            'state' => 'London',
            'postCode' => 'SW1A 1AA',
            'city' => 'London',
            'line1' => '10 Downing Street',
            'line2' => '',
            'line3' => '',
        ],
        'details' => [
            'weight' => ['unit' => 'KG', 'value' => 10],
            'declaredWeight' => ['unit' => 'KG', 'value' => 10],
            'cod' => ['currencyCode' => 'USD', 'amount' => 0],
            'customs' => ['currencyCode' => 'USD', 'amount' => 0],
            'deliveryCharges' => ['currencyCode' => 'USD', 'amount' => 0],
            'numberOfPieces' => 1,
            'pickupDate' => now()->toIso8601String(),
            'deliveryStatus' => '',
            'deliveryDate' => null,
            'deliveryAttempts' => 0,
            'shippingType' => 'INT',
            'productCategory' => 'Electronics',
            'productType' => 'Parcel',
            'descriptionOfGoods' => 'Phones',
            'dimensions' => ['length' => 10, 'width' => 10, 'height' => 10, 'unit' => 'CM'],
        ],
        'items' => [
            [
                'description' => 'Phone',
                'countryOfOrigin' => 'CN',
                'quantity' => 1,
                'hsCode' => '851712',
                'customsValue' => ['currencyCode' => 'USD', 'amount' => 100],
                'weight' => ['unit' => 'KG', 'value' => 1],
                'dimensions' => ['length' => 1, 'width' => 1, 'height' => 1, 'unit' => 'CM'],
            ],
        ],
    ];
}

function fakeEpglSuccess(): void
{
    Http::fake([
        'https://api.epgl.ae/api/v1/auth/authenticate' => Http::response([
            'accessToken' => 'token-abc',
            'tokenType' => 'bearer',
            'expiresIn' => 3600,
        ], 200),
        'https://api.epgl.ae/api/v1/shipment/create' => Http::response([
            'status' => 'Success',
            'message' => 'Shipment created successfully.',
            'data' => [
                'trackingNumber' => 'TRACK123',
                'uhawb' => 'AE20255236083162',
            ],
            'errors' => null,
            'correlationId' => 'corr-1',
        ], 201),
        'https://api.epgl.ae/api/v1/shipment/issueInvoice' => Http::response([
            'status' => 'Success',
            'message' => 'Shipment invoice issued successfully.',
            'data' => [],
            'errors' => null,
            'correlationId' => 'corr-2',
        ], 201),
    ]);
}

test('guests cannot view shipments', function () {
    $this->get(route('shipments.index'))->assertRedirect(route('login'));
});

test('shipments index can search multiple tracking numbers', function () {
    $user = User::factory()->create();

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-AAA',
        'payload' => shipmentPayload('TRACK-AAA'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-BBB',
        'payload' => shipmentPayload('TRACK-BBB'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-CCC',
        'payload' => shipmentPayload('TRACK-CCC'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    $this->actingAs($user)
        ->get(route('shipments.index', ['q' => "TRACK-AAA\nTRACK-CCC, TRACK-BBB"]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('shipments/Index')
            ->has('shipments.data', 3)
            ->where('filters.q', "TRACK-AAA\nTRACK-CCC, TRACK-BBB"));
});

test('shipments index search excludes non-matching tracking numbers', function () {
    $user = User::factory()->create();

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-KEEP',
        'payload' => shipmentPayload('TRACK-KEEP'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-SKIP',
        'payload' => shipmentPayload('TRACK-SKIP'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'form',
    ]);

    $this->actingAs($user)
        ->get(route('shipments.index', ['q' => 'TRACK-KEEP']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('shipments/Index')
            ->has('shipments.data', 1)
            ->where('shipments.data.0.tracking_number', 'TRACK-KEEP'));
});

test('shipment is saved as draft without calling EPGL', function () {
    $user = User::factory()->create();
    configureEpgl();
    Http::fake();

    $response = $this->actingAs($user)
        ->post(route('shipments.store'), shipmentPayload('TRACK-DRAFT'));

    $shipment = Shipment::query()->first();

    expect($shipment)->not->toBeNull()
        ->and($shipment->sync_status)->toBe(Shipment::STATUS_DRAFT)
        ->and($shipment->invoice_id)->toBeNull();

    Http::assertNothingSent();

    $response->assertRedirect(route('shipments.index', ['unlinked' => 1]));
});

test('csv upload creates draft shipments only', function () {
    $user = User::factory()->create();
    configureEpgl();
    Http::fake();
    Storage::fake('local');

    $csv = implode(',', [
        'tracking_number', 'uhawb', 'sender_name', 'sender_email', 'sender_phone', 'sender_second_phone',
        'sender_country_code', 'sender_state', 'sender_post_code', 'sender_city', 'sender_line1', 'sender_line2', 'sender_line3',
        'receiver_name', 'receiver_email', 'receiver_phone', 'receiver_second_phone',
        'receiver_country_code', 'receiver_state', 'receiver_post_code', 'receiver_city', 'receiver_line1', 'receiver_line2', 'receiver_line3',
        'weight_unit', 'weight_value', 'declared_weight_unit', 'declared_weight_value', 'number_of_pieces', 'pickup_date',
        'shipping_type', 'product_category', 'product_type', 'description_of_goods',
        'length', 'width', 'height', 'dimension_unit',
        'item_description', 'item_country_of_origin', 'item_quantity', 'item_hs_code',
        'item_customs_currency', 'item_customs_amount', 'item_weight_unit', 'item_weight_value',
    ])."\n".implode(',', [
        'TRACKCSV1', '', 'John', 'john@example.com', '+111', '', 'US', 'CA', '90210', 'City', 'Line1', '', '',
        'Jane', 'jane@example.com', '+222', '', 'GB', 'London', 'SW1', 'London', 'Line1', '', '',
        'KG', '1', 'KG', '1', '1', now()->toIso8601String(),
        'INT', 'Cat', 'Parcel', 'Goods',
        '10', '10', '10', 'CM',
        'Item', 'CN', '1', '123',
        'USD', '10', 'KG', '1',
    ]);

    $file = UploadedFile::fake()->createWithContent('shipments.csv', $csv);

    $this->actingAs($user)
        ->post(route('shipments.upload.store'), ['file' => $file])
        ->assertRedirect(route('shipments.index', ['unlinked' => 1]));

    expect(Shipment::query()->where('tracking_number', 'TRACKCSV1')->value('sync_status'))
        ->toBe(Shipment::STATUS_DRAFT);

    Http::assertNothingSent();
});

function csvShipmentLine(string $tracking): string
{
    return implode(',', [
        $tracking, '', 'John', 'john@example.com', '+111', '', 'US', 'CA', '90210', 'City', 'Line1', '', '',
        'Jane', 'jane@example.com', '+222', '', 'GB', 'London', 'SW1', 'London', 'Line1', '', '',
        'KG', '1', 'KG', '1', '1', now()->toIso8601String(),
        'INT', 'Cat', 'Parcel', 'Goods',
        '10', '10', '10', 'CM',
        'Item', 'CN', '1', '123',
        'USD', '10', 'KG', '1',
    ]);
}

function csvHeaderLine(): string
{
    return implode(',', [
        'tracking_number', 'uhawb', 'sender_name', 'sender_email', 'sender_phone', 'sender_second_phone',
        'sender_country_code', 'sender_state', 'sender_post_code', 'sender_city', 'sender_line1', 'sender_line2', 'sender_line3',
        'receiver_name', 'receiver_email', 'receiver_phone', 'receiver_second_phone',
        'receiver_country_code', 'receiver_state', 'receiver_post_code', 'receiver_city', 'receiver_line1', 'receiver_line2', 'receiver_line3',
        'weight_unit', 'weight_value', 'declared_weight_unit', 'declared_weight_value', 'number_of_pieces', 'pickup_date',
        'shipping_type', 'product_category', 'product_type', 'description_of_goods',
        'length', 'width', 'height', 'dimension_unit',
        'item_description', 'item_country_of_origin', 'item_quantity', 'item_hs_code',
        'item_customs_currency', 'item_customs_amount', 'item_weight_unit', 'item_weight_value',
    ]);
}

test('csv upload skips tracking numbers that already exist', function () {
    $user = User::factory()->create();
    configureEpgl();
    Http::fake();

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-DUP',
        'payload' => shipmentPayload('TRACK-DUP'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'csv',
    ]);

    $csv = csvHeaderLine()."\n".csvShipmentLine('TRACK-DUP')."\n".csvShipmentLine('TRACK-NEW');
    $file = UploadedFile::fake()->createWithContent('shipments.csv', $csv);

    $this->actingAs($user)
        ->post(route('shipments.upload.store'), ['file' => $file])
        ->assertRedirect(route('shipments.index', ['unlinked' => 1]))
        ->assertSessionHasErrors('file');

    expect(Shipment::query()->where('tracking_number', 'TRACK-DUP')->count())->toBe(1)
        ->and(Shipment::query()->where('tracking_number', 'TRACK-NEW')->exists())->toBeTrue();
});

test('csv upload rejects file when all tracking numbers are duplicates', function () {
    $user = User::factory()->create();
    configureEpgl();
    Http::fake();

    Shipment::query()->create([
        'user_id' => $user->id,
        'tracking_number' => 'TRACK-ONLY',
        'payload' => shipmentPayload('TRACK-ONLY'),
        'sync_status' => Shipment::STATUS_DRAFT,
        'source' => 'csv',
    ]);

    $csv = csvHeaderLine()."\n".csvShipmentLine('TRACK-ONLY');
    $file = UploadedFile::fake()->createWithContent('shipments.csv', $csv);

    $this->actingAs($user)
        ->from(route('shipments.upload'))
        ->post(route('shipments.upload.store'), ['file' => $file])
        ->assertRedirect(route('shipments.upload'))
        ->assertSessionHasErrors('file');

    expect(Shipment::query()->count())->toBe(1);
});

test('csv upload skips duplicate tracking numbers inside the same file', function () {
    $user = User::factory()->create();
    configureEpgl();
    Http::fake();

    $csv = csvHeaderLine()."\n".csvShipmentLine('TRACK-SAME')."\n".csvShipmentLine('TRACK-SAME');
    $file = UploadedFile::fake()->createWithContent('shipments.csv', $csv);

    $this->actingAs($user)
        ->post(route('shipments.upload.store'), ['file' => $file])
        ->assertRedirect(route('shipments.index', ['unlinked' => 1]))
        ->assertSessionHasErrors('file');

    expect(Shipment::query()->where('tracking_number', 'TRACK-SAME')->count())->toBe(1);
});
