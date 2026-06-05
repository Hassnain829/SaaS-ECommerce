<?php

namespace App\Services\Carriers\FedEx;

use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FedExHttpClient
{
    public function __construct(
        private readonly FedExConfig $config,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function postForm(
        string $environment,
        string $path,
        array $payload,
        array $headers = [],
        bool $retry = false,
        ?array $requestSummary = null,
    ): CarrierApiResult {
        return $this->request('post', $environment, $path, $payload, $headers, true, $retry, null, $requestSummary);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function postJson(
        string $environment,
        string $path,
        array $payload,
        array $headers = [],
        ?string $bearerToken = null,
        ?array $requestSummary = null,
    ): CarrierApiResult {
        return $this->request('post', $environment, $path, $payload, $headers, false, false, $bearerToken, $requestSummary);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $requestSummary
     */
    private function request(
        string $method,
        string $environment,
        string $path,
        array $payload,
        array $headers,
        bool $asForm = false,
        bool $retry = false,
        ?string $bearerToken = null,
        ?array $requestSummary = null,
    ): CarrierApiResult {
        $started = microtime(true);
        $requestId = (string) Str::uuid();
        $normalizedPath = '/'.ltrim($path, '/');
        $url = $this->config->baseUrl($environment).$normalizedPath;
        $summary = array_merge($requestSummary ?? [], [
            'endpoint' => $normalizedPath,
        ]);

        try {
            $request = $this->baseRequest($headers, $bearerToken, $retry);

            /** @var Response $response */
            $response = $asForm
                ? $request->asForm()->post($url, $payload)
                : $request->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $json = $response->json();
            $fedexTransactionId = $this->fedexTransactionId($json, $response);
            $responseSummary = $this->buildResponseSummary($response->status(), $json, $fedexTransactionId);

            if ($response->successful()) {
                return CarrierApiResult::success(
                    data: is_array($json) ? $json : null,
                    requestId: $fedexTransactionId ?: $requestId,
                    durationMs: $durationMs,
                    requestSummary: $summary,
                    responseSummary: $responseSummary,
                );
            }

            $message = $this->extractErrorMessage($json) ?? 'FedEx request failed.';

            return CarrierApiResult::failure(
                message: $message,
                code: (string) ($json['errors'][0]['code'] ?? $response->status()),
                requestId: $fedexTransactionId ?: $requestId,
                durationMs: $durationMs,
                requestSummary: $summary,
                responseSummary: $responseSummary,
            );
        } catch (Throwable) {
            return CarrierApiResult::failure(
                message: 'Unable to reach FedEx sandbox right now. Please try again.',
                code: 'transport_error',
                requestId: $requestId,
                durationMs: (int) round((microtime(true) - $started) * 1000),
                requestSummary: $summary,
                responseSummary: [
                    'http_status' => null,
                    'fedex_transaction_id' => null,
                ],
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function baseRequest(array $headers, ?string $bearerToken, bool $retry): PendingRequest
    {
        $request = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(array_merge([
                'x-customer-transaction-id' => (string) Str::uuid(),
                'X-locale' => 'en_US',
            ], $headers));

        if ($bearerToken) {
            $request = $request->withToken($bearerToken);
        }

        if ($retry) {
            $request = $request->retry(2, 200, throw: false);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function fedexTransactionId(?array $json, Response $response): ?string
    {
        if (is_array($json) && filled($json['transactionId'] ?? null)) {
            return (string) $json['transactionId'];
        }

        $header = $response->header('x-customer-transaction-id');

        return is_string($header) && $header !== '' ? $header : null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function buildResponseSummary(int $httpStatus, ?array $json, ?string $fedexTransactionId): array
    {
        $summary = [
            'http_status' => $httpStatus,
            'fedex_transaction_id' => $fedexTransactionId,
        ];

        if (is_array($json)) {
            $summary = array_merge($summary, $this->sanitizeResponse($json));
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractErrorMessage(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $errors = $json['errors'] ?? null;

        if (is_array($errors) && isset($errors[0]['message'])) {
            return (string) $errors[0]['message'];
        }

        return isset($json['message']) ? (string) $json['message'] : null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function sanitizeResponse(array $json): array
    {
        unset(
            $json['access_token'],
            $json['child_Key'],
            $json['child_key'],
            $json['childSecret'],
            $json['child_secret'],
            $json['transactionId'],
        );

        if (isset($json['output']) && is_array($json['output'])) {
            unset(
                $json['output']['child_Key'],
                $json['output']['child_key'],
                $json['output']['childSecret'],
                $json['output']['child_secret'],
                $json['output']['accountAuthToken'],
            );
        }

        return $json;
    }
}
