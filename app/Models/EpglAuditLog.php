<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $invoice_id
 * @property int|null $shipment_id
 * @property string $action
 * @property string $endpoint
 * @property string $method
 * @property string|null $tracking_number
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_body
 * @property int|null $http_status
 * @property bool $successful
 * @property string|null $correlation_id
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EpglAuditLog extends Model
{
    public const ACTION_CREATE_SHIPMENT = 'create_shipment';

    public const ACTION_ISSUE_INVOICE = 'issue_invoice';

    public const ACTION_AUTHENTICATE = 'authenticate';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'shipment_id',
        'action',
        'endpoint',
        'method',
        'tracking_number',
        'request_payload',
        'response_body',
        'http_status',
        'successful',
        'correlation_id',
        'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_body' => 'array',
            'successful' => 'boolean',
            'http_status' => 'integer',
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

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
