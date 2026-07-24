<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $invoice_id
 * @property string $tracking_number
 * @property string|null $uhawb
 * @property array<string, mixed> $payload
 * @property string $sync_status
 * @property string|null $sync_message
 * @property array<int, mixed>|null $sync_errors
 * @property string|null $correlation_id
 * @property Carbon|null $last_synced_at
 * @property string $source
 */
class Shipment extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'tracking_number',
        'uhawb',
        'payload',
        'sync_status',
        'sync_message',
        'sync_errors',
        'correlation_id',
        'last_synced_at',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sync_errors' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isDraft(): bool
    {
        return $this->sync_status === self::STATUS_DRAFT;
    }

    public function isFailed(): bool
    {
        return $this->sync_status === self::STATUS_FAILED;
    }

    public function canSubmitToEpgl(): bool
    {
        return $this->invoice_id !== null
            && in_array($this->sync_status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }
}
