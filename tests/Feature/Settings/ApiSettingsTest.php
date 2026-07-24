<?php

use App\Models\ApiSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('api settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api-settings.edit'))
        ->assertOk();
});

test('api settings can be saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('api-settings.update'), [
            'base_url' => 'https://api.epgl.ae',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('api-settings.edit'));

    $settings = ApiSetting::current();

    expect($settings->base_url)->toBe('https://api.epgl.ae')
        ->and($settings->client_id)->toBe('client-123')
        ->and($settings->client_secret)->toBe('secret-456')
        ->and($settings->isConfigured())->toBeTrue();
});

test('api connection can be tested', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://api.epgl.ae/api/v1/auth/authenticate' => Http::response([
            'accessToken' => 'token-abc',
            'tokenType' => 'bearer',
            'expiresIn' => 3600,
        ], 200),
    ]);

    $this->actingAs($user)
        ->from(route('api-settings.edit'))
        ->post(route('api-settings.test'), [
            'base_url' => 'https://api.epgl.ae',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ])
        ->assertRedirect(route('api-settings.edit'));
});
