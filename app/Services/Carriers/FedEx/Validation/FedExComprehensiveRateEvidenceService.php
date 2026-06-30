<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateAccessClassifier;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateResponseParser;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExComprehensiveRateEvidenceService
{
    public const EXPORT_FOLDER = '04_comprehensive_rates';

    public function __construct(
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExComprehensiveRateResponseParser $responseParser,
        private readonly FedExConfig $config,
    ) {}

    public function canonicalEvent(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->evidenceQuery->canonicalComprehensiveRateEvent($store, $account);
    }

    public function latestAccessBlocker(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->evidenceQuery->latestComprehensiveRateAccessBlocker($store, $account);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parsedResult(?CarrierApiEvent $event): ?array
    {
        if ($event === null) {
            return null;
        }

        $fixture = app(FedExTestCaseFixtureService::class)->comprehensiveRateQuoteCase();
        $responseBody = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : null;

        return $this->responseParser->parse(
            $responseBody,
            expectedServiceType: (string) ($fixture['expected_service_type'] ?? ''),
            expectedRateType: (string) data_get($event->response_summary, 'rate_type', $fixture['expected_rate_type'] ?? 'ACCOUNT'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceStatus(Store $store, CarrierAccount $account): array
    {
        $canonical = $this->canonicalEvent($store, $account);
        $blocker = $canonical === null ? $this->latestAccessBlocker($store, $account) : null;
        $displayEvent = $canonical ?? $blocker;
        $parsed = $this->parsedResult($displayEvent);

        return [
            'endpoint' => $this->config->comprehensiveRateQuotePath(),
            'fixture_case_key' => FedExTestCaseFixtureService::COMPREHENSIVE_RATE_CASE_KEY,
            'fixture_source' => data_get($displayEvent?->request_summary, 'fixture_source'),
            'transaction_status' => $this->transactionStatus($canonical, $blocker),
            'event' => $displayEvent,
            'canonical_event' => $canonical,
            'parsed' => $parsed,
            'display_amount' => $parsed['amount'] ?? data_get($displayEvent?->response_summary, 'amount'),
            'display_currency' => $parsed['currency'] ?? data_get($displayEvent?->response_summary, 'currency'),
            'display_service_type' => $parsed['service_type'] ?? data_get($displayEvent?->response_summary, 'service_type'),
            'display_rate_type' => $parsed['rate_type'] ?? data_get($displayEvent?->response_summary, 'rate_type'),
            'ui_matches_response' => $this->uiMatchesResponse($canonical),
            'screenshot_artifact' => $this->screenshotArtifact($store, $account, $canonical),
            'available_rates' => $parsed['available_rates'] ?? [],
        ];
    }

    public function uiMatchesResponse(?CarrierApiEvent $event): bool
    {
        if ($event === null || ! $event->isSuccessfulHttp()) {
            return false;
        }

        $parsed = $this->parsedResult($event);
        if ($parsed === null) {
            return false;
        }

        $summary = $event->response_summary ?? [];

        return $this->decimalMatches($parsed['amount'] ?? null, data_get($summary, 'amount'))
            && strtoupper((string) ($parsed['currency'] ?? '')) === strtoupper((string) data_get($summary, 'currency', ''))
            && strtoupper((string) ($parsed['service_type'] ?? '')) === strtoupper((string) data_get($summary, 'service_type', ''))
            && strtoupper((string) ($parsed['rate_type'] ?? '')) === strtoupper((string) data_get($summary, 'rate_type', ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionCheck(?CarrierApiEvent $canonical, ?CarrierApiEvent $blocker): array
    {
        if ($canonical !== null) {
            return [
                'key' => 'comprehensive_rate_transaction',
                'category' => 'api',
                'label' => 'Comprehensive Rates & Transit Times',
                'required' => true,
                'status' => 'passed',
                'explanation' => 'Successful comprehensive rate transaction recorded on the required endpoint.',
                'event_id' => $canonical->id,
            ];
        }

        if ($blocker !== null) {
            $accessState = (string) data_get($blocker->response_summary, 'access_state', 'blocked_access');
            $isEntitlement = $accessState === FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ENTITLEMENT
                || $blocker->error_code === 'fedex_comprehensive_rate_blocked_entitlement';

            return [
                'key' => 'comprehensive_rate_transaction',
                'category' => 'api',
                'label' => 'Comprehensive Rates & Transit Times',
                'required' => true,
                'status' => 'blocked',
                'explanation' => $isEntitlement
                    ? 'Blocked — FedEx access required. The request was sent to the required Comprehensive Rates endpoint. Review the sanitized response evidence and contact the FedEx validation or project-access team.'
                    : 'Blocked — FedEx access required. Comprehensive Rates returned HTTP '.($blocker->http_status ?? '403').' without a successful quote.',
                'event_id' => $blocker->id,
                'access_state' => $accessState,
                'fedex_error_code' => data_get($blocker->response_summary, 'fedex_error_code'),
            ];
        }

        return [
            'key' => 'comprehensive_rate_transaction',
            'category' => 'api',
            'label' => 'Comprehensive Rates & Transit Times',
            'required' => true,
            'status' => 'not_tested',
            'explanation' => 'Run the comprehensive rate quote test before final export.',
            'event_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uiMatchCheck(?CarrierApiEvent $canonical): array
    {
        if ($canonical === null) {
            return [
                'key' => 'comprehensive_rate_ui_match',
                'category' => 'evidence',
                'label' => 'Comprehensive rate UI/response match',
                'required' => true,
                'status' => 'not_tested',
                'explanation' => 'Run a successful comprehensive rate transaction first.',
                'event_id' => null,
            ];
        }

        $matches = $this->uiMatchesResponse($canonical);

        return [
            'key' => 'comprehensive_rate_ui_match',
            'category' => 'evidence',
            'label' => 'Comprehensive rate UI/response match',
            'required' => true,
            'status' => $matches ? 'passed' : 'failed',
            'explanation' => $matches
                ? 'Customer-facing rate panel matches the stored canonical response amount.'
                : 'Customer-facing rate amount does not match the stored canonical response.',
            'event_id' => $canonical->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function screenshotCheck(Store $store, CarrierAccount $account, ?CarrierApiEvent $canonical): array
    {
        if ($canonical === null) {
            return [
                'key' => 'comprehensive_rate_screenshot',
                'category' => 'artifact',
                'label' => 'Comprehensive rate result screenshot',
                'required' => true,
                'status' => 'not_tested',
                'explanation' => 'Upload a customer-facing rate result screenshot after a successful comprehensive rate run.',
                'event_id' => null,
            ];
        }

        $artifact = $this->screenshotArtifact($store, $account, $canonical);

        if ($artifact === null) {
            return [
                'key' => 'comprehensive_rate_screenshot',
                'category' => 'artifact',
                'label' => 'Comprehensive rate result screenshot',
                'required' => true,
                'status' => 'incomplete',
                'explanation' => 'Upload the comprehensive rate result screenshot linked to the canonical event.',
                'event_id' => $canonical->id,
            ];
        }

        $path = $artifact->absolutePath();
        $validFile = $path !== null && is_file($path) && filled($artifact->sha256) && hash_file('sha256', $path) === (string) $artifact->sha256;
        $metadataMatches = $this->decimalMatches(
            data_get($artifact->metadata_json, 'amount'),
            data_get($canonical->response_summary, 'amount'),
        ) && strtoupper((string) data_get($artifact->metadata_json, 'currency', '')) === strtoupper((string) data_get($canonical->response_summary, 'currency', ''));

        $passed = $validFile && $metadataMatches;

        return [
            'key' => 'comprehensive_rate_screenshot',
            'category' => 'artifact',
            'label' => 'Comprehensive rate result screenshot',
            'required' => true,
            'status' => $passed ? 'passed' : 'failed',
            'explanation' => $passed
                ? 'Comprehensive rate screenshot linked to the canonical event.'
                : 'Comprehensive rate screenshot is missing, tampered, or does not match the canonical event amount.',
            'event_id' => $canonical->id,
            'artifact_id' => $artifact->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportResultSummary(?CarrierApiEvent $event, bool $blocked = false): array
    {
        if ($event === null) {
            return ['status' => 'missing', 'submission_ready' => false];
        }

        if ($blocked || ! $event->isSuccessfulHttp()) {
            return array_filter([
                'scenario' => 'Comprehensive Rates & Transit Times',
                'endpoint' => $this->config->comprehensiveRateQuotePath(),
                'status' => data_get($event->response_summary, 'access_state', 'blocked_access'),
                'http_status' => $event->http_status,
                'fedex_error_code' => data_get($event->response_summary, 'fedex_error_code'),
                'fedex_error_message' => data_get($event->response_summary, 'fedex_error_message'),
                'event_id' => $event->id,
                'submission_ready' => false,
            ]);
        }

        return array_filter([
            'scenario' => 'Comprehensive Rates & Transit Times',
            'endpoint' => $this->config->comprehensiveRateQuotePath(),
            'http_status' => $event->http_status,
            'service_type' => data_get($event->response_summary, 'service_type'),
            'rate_type' => data_get($event->response_summary, 'rate_type'),
            'currency' => data_get($event->response_summary, 'currency'),
            'response_amount' => data_get($event->response_summary, 'amount'),
            'displayed_amount' => data_get($event->response_summary, 'ui_amount', data_get($event->response_summary, 'amount')),
            'ui_matches_response' => (bool) data_get($event->response_summary, 'ui_matches_response', false),
            'response_amount_path' => data_get($event->response_summary, 'response_amount_path'),
            'event_id' => $event->id,
            'submission_ready' => true,
        ]);
    }

    private function transactionStatus(?CarrierApiEvent $canonical, ?CarrierApiEvent $blocker): string
    {
        if ($canonical !== null) {
            return 'passed';
        }

        if ($blocker !== null) {
            return 'blocked';
        }

        return 'not_tested';
    }

    private function screenshotArtifact(Store $store, CarrierAccount $account, ?CarrierApiEvent $canonical): ?FedExValidationArtifact
    {
        if ($canonical === null) {
            return null;
        }

        return FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('carrier_api_event_id', $canonical->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_COMPREHENSIVE_RATE_SCREENSHOT)
            ->where('artifact_type', FedExValidationArtifact::TYPE_COMPREHENSIVE_RATE_RESULT_UI)
            ->latest('id')
            ->first();
    }

    private function decimalMatches(mixed $left, mixed $right): bool
    {
        $leftNormalized = $this->normalizeDecimal($left);
        $rightNormalized = $this->normalizeDecimal($right);

        return $leftNormalized !== null
            && $rightNormalized !== null
            && $leftNormalized === $rightNormalized;
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
