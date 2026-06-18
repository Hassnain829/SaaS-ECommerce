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
        $normalizedPath = '/'.ltrim($path, '/');
        $url = $this->config->baseUrl($environment).$normalizedPath;
        $summary = array_merge($requestSummary ?? [], [
            'endpoint' => $normalizedPath,
            'environment' => $environment,
        ]);

        try {
            $request = $this->baseRequest($headers, $bearerToken, $retry, $asForm);

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
            $message = $this->merchantFriendlyFailureMessage($response->status(), $normalizedPath, $message);
            $errorCode = is_array($json)
                ? (string) (data_get($json, 'errors.0.code') ?? $response->status())
                : (string) $response->status();

            return CarrierApiResult::failure(
                message: $message,
                code: $errorCode,
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
    private function baseRequest(array $headers, ?string $bearerToken, bool $retry, bool $asForm = false): PendingRequest
    {
        $defaultHeaders = [
            'x-customer-transaction-id' => (string) Str::uuid(),
            'X-locale' => 'en_US',
        ];

        if (! $asForm) {
            $defaultHeaders['Content-Type'] = 'application/json';
        }

        $request = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(array_merge($defaultHeaders, $headers));

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
            return 'FedEx rejected this rate quote because the registered sandbox account/child credentials are not authorized for Comprehensive Rates and Transit Times API in this environment. Confirm the Rates and Transit Times API is enabled on the FedEx project, the sandbox validation account is allowed for rating, and the selected test case/account supports rating.';
        }

        if ($httpStatus >= 500 && str_contains($path, '/availability/v1/packageandserviceoptions')) {
            return 'FedEx returned a temporary service-availability error for this route. Your FedEx credentials are connected, but FedEx could not return service options for this origin/destination right now. Try another valid ZIP/state/city combination or retry later.';
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
