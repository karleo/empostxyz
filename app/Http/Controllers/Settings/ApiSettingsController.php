<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestApiSettingsRequest;
use App\Http\Requests\Settings\UpdateApiSettingsRequest;
use App\Models\ApiSetting;
use App\Services\Epgl\EpglAuthService;
use App\Support\EpglBaseUrl;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApiSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = ApiSetting::current();

        return Inertia::render('settings/Api', [
            'settings' => [
                'base_url' => $settings->base_url ?? '',
                'client_id' => $settings->client_id ?? '',
                'has_client_secret' => filled($settings->client_secret),
                'is_configured' => $settings->isConfigured(),
            ],
        ]);
    }

    public function update(UpdateApiSettingsRequest $request): RedirectResponse
    {
        $settings = ApiSetting::current();
        $data = $request->validated();

        $settings->base_url = EpglBaseUrl::normalize($data['base_url']);
        $settings->client_id = $data['client_id'];

        if (filled($data['client_secret'] ?? null)) {
            $settings->client_secret = $data['client_secret'];
        }

        $settings->save();
        $settings->clearTokenCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API settings saved.')]);

        return to_route('api-settings.edit');
    }

    public function test(TestApiSettingsRequest $request): RedirectResponse
    {
        $settings = ApiSetting::current();
        $data = $request->validated();

        $secret = $data['client_secret'] ?? null;
        if (! filled($secret)) {
            $secret = $settings->client_secret;
        }

        if (! filled($secret)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Client secret is required to test the connection.'),
            ]);

            return back();
        }

        $temp = new ApiSetting;
        $temp->forceFill([
            'id' => $settings->id ?: 1,
            'base_url' => EpglBaseUrl::normalize($data['base_url']),
            'client_id' => $data['client_id'],
        ]);
        $temp->client_secret = $secret;

        $result = (new EpglAuthService($temp))->testConnection();

        Inertia::flash('toast', [
            'type' => $result->successful ? 'success' : 'error',
            'message' => $result->message ?? ($result->successful ? 'Connection successful.' : 'Connection failed.'),
        ]);

        return back();
    }
}
