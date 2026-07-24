<?php

namespace App\Services\Epgl;

readonly class EpglResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<int, mixed>|null  $errors
     */
    public function __construct(
        public bool $successful,
        public int $httpStatus,
        public ?string $status,
        public ?string $message,
        public ?array $data,
        public ?array $errors,
        public ?string $correlationId,
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromHttp(int $httpStatus, array $json): self
    {
        $status = isset($json['status']) ? (string) $json['status'] : null;
        $successful = $httpStatus >= 200 && $httpStatus < 300
            && ($status === null || strcasecmp($status, 'Success') === 0 || strcasecmp($status, 'success') === 0);

        return new self(
            successful: $successful,
            httpStatus: $httpStatus,
            status: $status,
            message: isset($json['message']) ? (string) $json['message'] : null,
            data: isset($json['data']) && is_array($json['data']) ? $json['data'] : null,
            errors: isset($json['errors']) && is_array($json['errors']) ? $json['errors'] : null,
            correlationId: isset($json['correlationId']) ? (string) $json['correlationId'] : null,
        );
    }

    public static function failure(string $message, int $httpStatus = 0): self
    {
        return new self(
            successful: false,
            httpStatus: $httpStatus,
            status: 'Failure',
            message: $message,
            data: null,
            errors: null,
            correlationId: null,
        );
    }
}
