<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierApiEvent;

final class FedExValidationAuthorizationEvidenceRules
{
    public function satisfiesRequirements(CarrierApiEvent $event, string $expectedGrantType): bool
    {
        if ($this->isCachedAuthorizationEvent($event)) {
            return false;
        }

        if (! $event->hasCompleteEvidence()) {
            return false;
        }

        if (strtoupper((string) $event->http_method) !== 'POST') {
            return false;
        }

        $endpoint = (string) ($event->endpoint ?? data_get($event->request_summary, 'endpoint', ''));
        if (! str_contains($endpoint, '/oauth/token')) {
            return false;
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED || ! $event->isSuccessfulHttp()) {
            return false;
        }

        $request = is_array($event->request_body_encrypted) ? $event->request_body_encrypted : [];
        $response = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : [];

        return $this->validRequest($request, $expectedGrantType)
            && $this->validResponse($response);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function validRequest(array $request, string $expectedGrantType): bool
    {
        $grantType = strtolower((string) ($request['grant_type'] ?? ''));

        if ($grantType !== strtolower($expectedGrantType)) {
            return false;
        }

        if ($grantType === 'client_credentials') {
            return $this->isRedactedCredential($request, 'client_id')
                && $this->isRedactedCredential($request, 'client_secret')
                && ! $this->hasTopLevelField($request, 'child_key')
                && ! $this->hasTopLevelField($request, 'child_secret');
        }

        if ($grantType === 'csp_credentials') {
            return $this->isRedactedCredential($request, 'client_id')
                && $this->isRedactedCredential($request, 'client_secret')
                && $this->isRedactedCredential($request, 'child_key')
                && $this->isRedactedCredential($request, 'child_secret');
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function validResponse(array $response): bool
    {
        if (! $this->isRedactedCredential($response, 'access_token')) {
            return false;
        }

        if (strtolower((string) ($response['token_type'] ?? '')) !== 'bearer') {
            return false;
        }

        $expiresIn = $response['expires_in'] ?? null;

        return is_numeric($expiresIn);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isRedactedCredential(array $payload, string $key): bool
    {
        return array_key_exists($key, $payload) && $payload[$key] === '[REDACTED]';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasTopLevelField(array $payload, string $key): bool
    {
        return array_key_exists($key, $payload);
    }

    private function isCachedAuthorizationEvent(CarrierApiEvent $event): bool
    {
        return (bool) data_get($event->request_summary, 'cached')
            || (bool) data_get($event->response_summary, 'cached');
    }
}
