<?php

namespace App\Services\Carriers\FedEx;

use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExApiEvidenceData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FedExHttpClient
{
    public function __construct(
        private readonly FedExConfig $config,
    ) {}

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
        return $this->request(
            'post',
            $environment,
            $path,
            $payload,
            $headers,
            false,
            false,
            self::normalizeBearerToken($bearerToken),
            $requestSummary,
        );
    }

    public static function normalizeBearerToken(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $trimmed = trim($token);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with(strtolower($trimmed), 'bearer ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        return $trimmed !== '' ? $trimmed : null;
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
        $customerTransactionId = (string) ($headers['x-customer-transaction-id'] ?? Str::uuid());
        $normalizedPath = '/'.ltrim($path, '/');
        $url = $this->config->baseUrl($environment).$normalizedPath;
        $summary = array_merge($requestSummary ?? [], [
            'endpoint' => $normalizedPath,
            'environment' => $environment,
            'customer_transaction_id' => $customerTransactionId,
        ]);

        $outboundHeaders = array_merge([
            'x-customer-transaction-id' => $customerTransactionId,
            'X-locale' => 'en_US',
        ], $headers);

        $maxAttempts = str_contains($normalizedPath, '/ship/v1/') ? 3 : 1;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $request = $this->baseRequest($outboundHeaders, $bearerToken, $retry, $asForm);

                /** @var Response $response */
                $response = $asForm
                    ? $request->asForm()->post($url, $payload)
                    : $request->post($url, $payload);

                $durationMs = (int) round((microtime(true) - $started) * 1000);
                $json = $response->json();
                $fedexTransactionId = $this->fedexTransactionId($json, $response) ?? $customerTransactionId;
                $responseSummary = $this->buildResponseSummary($response->status(), $json, $fedexTransactionId);
                $responseSummary['customer_transaction_id'] = $customerTransactionId;
                if ($maxAttempts > 1) {
                    $responseSummary['ship_retry_attempt'] = $attempt;
                    $responseSummary['ship_retry_max_attempts'] = $maxAttempts;
                }
                $evidenceHeaders = array_merge($outboundHeaders, $bearerToken ? ['Authorization' => 'Bearer [present]'] : []);
                $evidence = $this->buildEvidence(
                    endpoint: $normalizedPath,
                    httpMethod: strtoupper($method),
                    requestHeaders: $evidenceHeaders,
                    payload: $payload,
                    response: $response,
                    responseBody: $json,
                    fedexTransactionId: $fedexTransactionId,
                );

                if ($response->successful()) {
                    return CarrierApiResult::success(
                        data: is_array($json) ? $json : null,
                        requestId: $fedexTransactionId ?: $requestId,
                        durationMs: $durationMs,
                        requestSummary: $summary,
                        responseSummary: $responseSummary,
                        evidence: $evidence,
                    );
                }

                $httpStatus = $response->status();
                if ($attempt < $maxAttempts && $httpStatus >= 502 && $httpStatus <= 503) {
                    usleep(2_000_000);

                    continue;
                }

                $message = $this->extractErrorMessage($json) ?? 'FedEx request failed.';
                $message = $this->merchantFriendlyFailureMessage($httpStatus, $normalizedPath, $message);
                $errorCode = is_array($json)
                    ? (string) (data_get($json, 'errors.0.code') ?? $httpStatus)
                    : (string) $httpStatus;

                return CarrierApiResult::failure(
                    message: $message,
                    code: $errorCode,
                    requestId: $fedexTransactionId ?: $requestId,
                    durationMs: $durationMs,
                    requestSummary: $summary,
                    responseSummary: $responseSummary,
                    evidence: $evidence,
                );
            }
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
                    'customer_transaction_id' => $customerTransactionId,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $requestHeaders
     */
    private function buildEvidence(
        string $endpoint,
        string $httpMethod,
        array $requestHeaders,
        array $payload,
        Response $response,
        mixed $responseBody,
        ?string $fedexTransactionId,
    ): FedExApiEvidenceData {
        return new FedExApiEvidenceData(
            $endpoint,
            $httpMethod,
            $requestHeaders,
            $payload,
            $this->safeResponseHeaders($response),
            is_array($responseBody)
                ? $responseBody
                : ['raw' => is_string($response->body()) ? mb_substr($response->body(), 0, 4096) : null],
            $response->status(),
            $fedexTransactionId,
        );
    }

    /**
     * @return array<string, string>
     */
    private function safeResponseHeaders(Response $response): array
    {
        $allowed = [
            'content-type',
            'x-customer-transaction-id',
            'date',
            'server',
        ];

        $headers = [];

        foreach ($allowed as $header) {
            $value = $response->header($header);
            if (is_string($value) && $value !== '') {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function baseRequest(array $headers, ?string $bearerToken, bool $retry, bool $asForm = false): PendingRequest
    {
        if (! isset($headers['x-customer-transaction-id'])) {
            $headers['x-customer-transaction-id'] = (string) Str::uuid();
        }

        if (! isset($headers['X-locale'])) {
            $headers['X-locale'] = 'en_US';
        }

        if (! $asForm && ! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $request = Http::timeout(20)
            ->acceptJson()
            ->withHeaders($headers);

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
            $summary = array_merge($summary, $this->sanitizeResponse($json, $httpStatus));
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
    private function sanitizeResponse(array $json, int $httpStatus): array
    {
        $summary = [];

        if (isset($json['errors']) && is_array($json['errors'])) {
            $summary['errors'] = $this->sanitizeFedExErrors($json['errors']);
        }

        if (isset($json['output']) && is_array($json['output']) && $httpStatus >= 200 && $httpStatus < 300) {
            $output = $json['output'];
            unset(
                $output['child_Key'],
                $output['child_key'],
                $output['childSecret'],
                $output['child_secret'],
                $output['accountAuthToken'],
                $output['customerKey'],
                $output['customerPassword'],
            );

            if (isset($output['mfaOptions']) && is_array($output['mfaOptions'])) {
                $output['mfaOptions'] = array_map(static function (mixed $item): mixed {
                    if (! is_array($item)) {
                        return $item;
                    }

                    unset($item['accountAuthToken']);

                    return $item;
                }, $output['mfaOptions']);
            }

            if ($output !== []) {
                $summary['output_summary'] = FedExMerchantCheckPresenter::compactOutputSummary($output);
            }
        }

        return $summary;
    }

    private function merchantFriendlyFailureMessage(int $httpStatus, string $path, string $defaultMessage): string
    {
        if ($httpStatus === 401) {
            return 'FedEx rejected the OAuth token for this request. Reconnect the FedEx credentials or verify the API key, secret, environment, and project permissions.';
        }

        if ($httpStatus === 403 && str_contains($path, '/rate/v1/rates/quotes')) {
            return 'FedEx authorization blocked: the connected sandbox account/child credentials are not entitled for Comprehensive Rates and Transit Times in this environment. This is a FedEx entitlement blocker — confirm Rates API access with FedEx support before resubmitting validation evidence.';
        }

        if ($httpStatus === 403 && str_contains($path, '/ship/v1/')) {
            return 'FedEx authorization blocked: the connected sandbox account/child credentials are not entitled for Ship API in this environment. This is a FedEx entitlement blocker — confirm Ship API access with FedEx support before resubmitting validation evidence.';
        }

        if ($httpStatus >= 500 && str_contains($path, '/availability/v1/packageandserviceoptions')) {
            return 'FedEx returned a temporary service-availability error for this route. Your FedEx credentials are connected, but FedEx could not return service options for this origin/destination right now. Try another valid ZIP/state/city combination or retry later.';
        }

        if ($httpStatus >= 500 && str_contains($path, '/ship/v1/')) {
            return 'FedEx Ship sandbox returned a temporary server error (HTTP '.$httpStatus.'). This is usually a FedEx-side outage or gateway issue, not a local payload defect. Wait a few minutes and retry the same locked test case. If it persists, check FedEx Developer Portal API Status or contact FedEx support with the transaction ID.';
        }

        return $defaultMessage;
    }

    /**
     * @param  array<int, mixed>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeFedExErrors(array $errors): array
    {
        $sanitized = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                $sanitized[] = ['message' => (string) $error];

                continue;
            }

            $entry = [];

            foreach (['code', 'message', 'parameterList', 'parameter', 'field', 'path'] as $key) {
                if (array_key_exists($key, $error)) {
                    $entry[$key] = $error[$key];
                }
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }
}
