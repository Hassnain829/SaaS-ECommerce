<?php

namespace App\Services\Carriers\DTO;

final class CarrierApiResult
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $requestSummary
     * @param  array<string, mixed>|null  $responseSummary
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?array $data = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $requestId = null,
        public readonly ?int $durationMs = null,
        public readonly ?array $requestSummary = null,
        public readonly ?array $responseSummary = null,
    ) {
    }

    public static function success(
        ?array $data = null,
        ?string $requestId = null,
        ?int $durationMs = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
    ): self {
        return new self(
            success: true,
            data: $data,
            requestId: $requestId,
            durationMs: $durationMs,
            requestSummary: $requestSummary,
            responseSummary: $responseSummary,
        );
    }

    public static function failure(
        string $message,
        ?string $code = null,
        ?string $requestId = null,
        ?int $durationMs = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
    ): self {
        return new self(
            success: false,
            errorCode: $code,
            errorMessage: $message,
            requestId: $requestId,
            durationMs: $durationMs,
            requestSummary: $requestSummary,
            responseSummary: $responseSummary,
        );
    }
}
