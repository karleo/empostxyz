<?php

namespace App\Services\Epgl;

use App\Models\ApiSetting;
use App\Support\EpglBaseUrl;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EpglAuthService
{
    public function __construct(
        private readonly ApiSetting $settings,
    ) {}

    public static function fromCurrentSettings(): self
    {
        return new self(ApiSetting::current());
    }

    /**
     * @throws RuntimeException
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (! $this->settings->isConfigured()) {
            throw new RuntimeException('Configure API settings first (base URL, client ID, and client secret).');
        }

        $cacheKey = $this->settings->tokenCacheKey();

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $authUrl = $this->endpoint('/api/v1/auth/authenticate');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($authUrl, [
                'clientId' => $this->settings->client_id,
                'clientSecret' => $this->settings->client_secret,
            ]);

        if (! $response->successful()) {
            $detail = $response->json('message')
                ?? (filled($response->body()) ? $response->body() : null)
                ?? ('HTTP '.$response->status());

            throw new RuntimeException(
                "EPGL authentication failed ({$detail}). Tried: {$authUrl}"
            );
        }

        $accessToken = $response->json('accessToken');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('EPGL authentication response did not include an access token.');
        }

        $expiresIn = (int) ($response->json('expiresIn') ?? 3600);
        $ttl = max(60, $expiresIn - 60);

        Cache::put($cacheKey, $accessToken, $ttl);

        return $accessToken;
    }

    public function testConnection(): EpglResponse
    {
        try {
            $this->getAccessToken(forceRefresh: true);

            return new EpglResponse(
                successful: true,
                httpStatus: 200,
                status: 'Success',
                message: 'Authentication successful. Token received.',
                data: null,
                errors: null,
                correlationId: null,
            );
        } catch (RuntimeException|RequestException $e) {
            return EpglResponse::failure($e->getMessage());
        }
    }

    public function endpoint(string $path): string
    {
        $base = EpglBaseUrl::normalize($this->settings->base_url);

        return $base.'/'.ltrim($path, '/');
    }

    public function settings(): ApiSetting
    {
        return $this->settings;
    }
}
