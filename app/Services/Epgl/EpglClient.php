<?php

namespace App\Services\Epgl;

use App\Models\EpglAuditLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EpglClient
{
    /**
     * @var array{
     *     user_id?: int|null,
     *     invoice_id?: int|null,
     *     shipment_id?: int|null,
     *     tracking_number?: string|null,
     * }
     */
    private array $auditContext = [];

    public function __construct(
        private readonly EpglAuthService $auth,
        private readonly EpglAuditLogger $auditor = new EpglAuditLogger,
    ) {}

    public static function make(): self
    {
        return new self(EpglAuthService::fromCurrentSettings());
    }

    /**
     * @param  array{
     *     user_id?: int|null,
     *     invoice_id?: int|null,
     *     shipment_id?: int|null,
     *     tracking_number?: string|null,
     * }  $context
     */
    public function withAuditContext(array $context): self
    {
        $clone = clone $this;
        $clone->auditContext = array_merge($this->auditContext, $context);

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createShipment(array $payload): EpglResponse
    {
        return $this->post(
            '/api/v1/shipment/create',
            $payload,
            EpglAuditLog::ACTION_CREATE_SHIPMENT,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issueInvoice(array $payload): EpglResponse
    {
        return $this->post(
            '/api/v1/shipment/issueInvoice',
            $payload,
            EpglAuditLog::ACTION_ISSUE_INVOICE,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload, string $action): EpglResponse
    {
        $endpoint = $this->auth->endpoint($path);

        try {
            $response = $this->send($path, $payload);

            if ($response->status() === 401) {
                $this->auth->getAccessToken(forceRefresh: true);
                $response = $this->send($path, $payload);
            }

            if ($response->status() === 429) {
                $result = EpglResponse::failure(
                    'Rate limited by EPGL. Please wait and try again.',
                    429,
                );
                $this->writeAudit($action, $endpoint, $payload, $result);

                return $result;
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];
            $result = EpglResponse::fromHttp($response->status(), $json);
            $this->writeAudit($action, $endpoint, $payload, $result, $json);

            return $result;
        } catch (RuntimeException $e) {
            $result = EpglResponse::failure($e->getMessage());
            $this->writeAudit($action, $endpoint, $payload, $result);

            return $result;
        } catch (ConnectionException $e) {
            $result = EpglResponse::failure('Unable to reach EPGL API: '.$e->getMessage());
            $this->writeAudit($action, $endpoint, $payload, $result);

            return $result;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $rawResponse
     */
    private function writeAudit(
        string $action,
        string $endpoint,
        array $payload,
        EpglResponse $result,
        ?array $rawResponse = null,
    ): void {
        $tracking = $this->auditContext['tracking_number']
            ?? (isset($payload['trackingNumber']) && is_string($payload['trackingNumber'])
                ? $payload['trackingNumber']
                : null);

        $this->auditor->log([
            'user_id' => $this->auditContext['user_id'] ?? null,
            'invoice_id' => $this->auditContext['invoice_id'] ?? null,
            'shipment_id' => $this->auditContext['shipment_id'] ?? null,
            'action' => $action,
            'endpoint' => $endpoint,
            'method' => 'POST',
            'tracking_number' => $tracking,
            'request_payload' => $payload,
            'response_body' => $rawResponse ?? [
                'status' => $result->status,
                'message' => $result->message,
                'data' => $result->data,
                'errors' => $result->errors,
                'correlationId' => $result->correlationId,
            ],
            'http_status' => $result->httpStatus ?: null,
            'successful' => $result->successful,
            'correlation_id' => $result->correlationId,
            'message' => $result->message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     * @throws ConnectionException
     */
    private function send(string $path, array $payload): Response
    {
        $token = $this->auth->getAccessToken();

        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(60)
            ->post($this->auth->endpoint($path), $payload);
    }
}
