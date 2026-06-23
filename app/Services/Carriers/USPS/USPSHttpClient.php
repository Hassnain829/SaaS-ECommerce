<?php

namespace App\Services\Carriers\USPS;

use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class USPSHttpClient
{
    public function __construct(
        private readonly USPSConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function postJson(
        string $path,
        array $payload,
        array $headers = [],
        ?string $bearerToken = null,
        ?array $requestSummary = null,
    ): CarrierApiResult {
        return $this->request('post', $path, $payload, $headers, false, $bearerToken, $requestSummary);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function getJson(
        string $path,
        array $query = [],
        array $headers = [],
        ?string $bearerToken = null,
        ?array $requestSummary = null,
    ): CarrierApiResult {
        return $this->request('get', $path, $query, $headers, true, $bearerToken, $requestSummary);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function request(
        string $method,
        string $path,
        array $payload,
        array $headers,
        bool $asQuery,
        ?string $bearerToken,
        ?array $requestSummary,
    ): CarrierApiResult {
        $started = microtime(true);
        $requestId = (string) Str::uuid();
        $normalizedPath = '/'.ltrim($path, '/');
        $url = $this->config->baseUrl().$normalizedPath;
        $summary = array_merge($requestSummary ?? [], [
            'endpoint' => $normalizedPath,
            'environment' => $this->config->environment(),
        ]);

        try {
            $request = $this->baseRequest($headers, $bearerToken);

            /** @var Response $response */
            $response = $method === 'get'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $json = $response->json();
            $responseSummary = $this->buildResponseSummary($response->status(), $json);

            if ($response->successful()) {
                return CarrierApiResult::success(
                    data: is_array($json) ? $json : null,
                    requestId: $requestId,
                    durationMs: $durationMs,
                    requestSummary: $summary,
                    responseSummary: $responseSummary,
                );
            }

            return CarrierApiResult::failure(
                message: $this->extractErrorMessage($json) ?? 'USPS request failed.',
                code: (string) ($json['error']['code'] ?? $json['code'] ?? $response->status()),
                requestId: $requestId,
                durationMs: $durationMs,
                requestSummary: $summary,
                responseSummary: $responseSummary,
            );
        } catch (Throwable) {
            return CarrierApiResult::failure(
                message: 'Unable to reach USPS right now. Please try again.',
                code: 'transport_error',
                requestId: $requestId,
                durationMs: (int) round((microtime(true) - $started) * 1000),
                requestSummary: $summary,
                responseSummary: [
                    'http_status' => null,
                ],
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function baseRequest(array $headers, ?string $bearerToken): PendingRequest
    {
        $request = Http::connectTimeout($this->config->connectTimeout())
            ->timeout($this->config->requestTimeout())
            ->acceptJson()
            ->withHeaders(array_merge([
                'Accept' => 'application/json',
            ], $headers));

        if ($bearerToken) {
            $request = $request->withToken($bearerToken);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function buildResponseSummary(int $httpStatus, ?array $json): array
    {
        $summary = ['http_status' => $httpStatus];

        if (! is_array($json)) {
            return $summary;
        }

        if (isset($json['errors']) && is_array($json['errors'])) {
            $summary['errors'] = array_map(function ($error) {
                if (! is_array($error)) {
                    return ['message' => (string) $error];
                }

                return array_filter([
                    'code' => $error['code'] ?? null,
                    'message' => $error['message'] ?? null,
                    'detail' => $error['detail'] ?? null,
                    'field' => $error['field'] ?? null,
                ], fn ($value) => $value !== null);
            }, $json['errors']);
        }

        if (isset($json['error']) && is_array($json['error'])) {
            $summary['error'] = array_filter([
                'code' => $json['error']['code'] ?? null,
                'message' => $json['error']['message'] ?? null,
            ], fn ($value) => $value !== null);
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

        if (isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        if (isset($json['errors'][0]['message'])) {
            return (string) $json['errors'][0]['message'];
        }

        return isset($json['message']) ? (string) $json['message'] : null;
    }
}
