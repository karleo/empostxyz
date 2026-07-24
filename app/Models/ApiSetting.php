<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string|null $base_url
 * @property string|null $client_id
 * @property string|null $client_secret
 */
class ApiSetting extends Model
{
    protected $fillable = [
        'base_url',
        'client_id',
        'client_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isConfigured(): bool
    {
        return filled($this->base_url)
            && filled($this->client_id)
            && filled($this->client_secret);
    }

    public function tokenCacheKey(): string
    {
        return 'epgl.access_token.'.$this->id.'.'.md5(($this->base_url ?? '').'|'.($this->client_id ?? ''));
    }

    public function clearTokenCache(): void
    {
        Cache::forget($this->tokenCacheKey());
    }
}
