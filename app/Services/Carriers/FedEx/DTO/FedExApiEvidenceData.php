<?php

namespace App\Services\Carriers\FedEx\DTO;

final class FedExApiEvidenceData
{
    /**
     * @param  array<string, mixed>  $requestHeaders
     * @param  array<string, mixed>  $responseHeaders
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $httpMethod,
        public readonly array $requestHeaders,
        public readonly mixed $requestBody,
        public readonly array $responseHeaders,
        public readonly mixed $responseBody,
        public readonly ?int $httpStatus,
        public readonly ?string $fedexTransactionId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'http_method' => $this->httpMethod,
            'request_headers' => $this->requestHeaders,
            'request_body' => $this->requestBody,
            'response_headers' => $this->responseHeaders,
            'response_body' => $this->responseBody,
            'http_status' => $this->httpStatus,
            'fedex_transaction_id' => $this->fedexTransactionId,
        ];
    }
}
