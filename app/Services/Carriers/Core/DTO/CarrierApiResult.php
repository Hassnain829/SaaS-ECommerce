<?php

namespace App\Services\Carriers\Core\DTO;

use App\Services\Carriers\FedEx\DTO\FedExApiEvidenceData;

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
        public readonly ?FedExApiEvidenceData $evidence = null,
    ) {}

    public static function success(
        ?array $data = null,
        ?string $requestId = null,
        ?int $durationMs = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
        ?FedExApiEvidenceData $evidence = null,
    ): self {
        return new self(
            success: true,
            data: $data,
            requestId: $requestId,
            durationMs: $durationMs,
            requestSummary: $requestSummary,
            responseSummary: $responseSummary,
            evidence: $evidence,
        );
    }

    public static function failure(
        string $message,
        ?string $code = null,
        ?string $requestId = null,
        ?int $durationMs = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
        ?FedExApiEvidenceData $evidence = null,
    ): self {
        return new self(
            success: false,
            errorCode: $code,
            errorMessage: $message,
            requestId: $requestId,
            durationMs: $durationMs,
            requestSummary: $requestSummary,
            responseSummary: $responseSummary,
            evidence: $evidence,
        );
    }

    public function withEvidence(?FedExApiEvidenceData $evidence): self
    {
        return $this->copyWith(evidence: $evidence);
    }

    /**
     * Preserve complete evidence and transport metadata when normalizing API results.
     */
    public function copyWith(
        ?bool $success = null,
        ?array $data = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $requestId = null,
        ?int $durationMs = null,
        ?array $requestSummary = null,
        ?array $responseSummary = null,
        ?FedExApiEvidenceData $evidence = null,
        bool $preserveEvidence = true,
    ): self {
        return new self(
            success: $success ?? $this->success,
            data: $data ?? $this->data,
            errorCode: $errorCode ?? $this->errorCode,
            errorMessage: $errorMessage ?? $this->errorMessage,
            requestId: $requestId ?? $this->requestId,
            durationMs: $durationMs ?? $this->durationMs,
            requestSummary: $requestSummary ?? $this->requestSummary,
            responseSummary: $responseSummary ?? $this->responseSummary,
            evidence: $evidence ?? ($preserveEvidence ? $this->evidence : null),
        );
    }
}
