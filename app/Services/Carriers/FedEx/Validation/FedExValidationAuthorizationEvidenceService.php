<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorParentOAuthService;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExValidationAuthorizationEvidenceService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExIntegratorParentOAuthService $parentOAuth,
        private readonly FedExIntegratorChildOAuthService $childOAuth,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    /**
     * @return array{
     *     blocked: bool,
     *     message: ?string,
     *     parent: ?array{result: CarrierApiResult, event: CarrierApiEvent},
     *     child: ?array{result: CarrierApiResult, event: CarrierApiEvent},
     *     child_skipped_reason: ?string
     * }
     */
    public function runBoth(CarrierAccount $account): array
    {
        abort_unless($this->config->validationModeEnabled(), 403);
        abort_unless($account->usesFedExIntegratorProvider(), 404);

        if (! $this->config->isConfigured($account->environment)) {
            return [
                'blocked' => true,
                'message' => 'FedEx platform credentials are not configured. Contact the platform administrator.',
                'parent' => null,
                'child' => null,
                'child_skipped_reason' => null,
            ];
        }

        if (! $account->hasLegacyFedExChildCredentials()) {
            return [
                'blocked' => true,
                'message' => 'Child credentials are not available for this connected FedEx account. Complete account registration before running child authorization evidence.',
                'parent' => null,
                'child' => null,
                'child_skipped_reason' => null,
            ];
        }

        $parent = $this->runParentAuthorization($account);

        if (! $parent['result']->success) {
            return [
                'blocked' => false,
                'message' => 'Parent authorization failed. Child authorization was not run because the parent authorization failed.',
                'parent' => $parent,
                'child' => null,
                'child_skipped_reason' => 'Parent authorization failed.',
            ];
        }

        $child = $this->runChildAuthorization($account);

        return [
            'blocked' => false,
            'message' => $child['result']->success
                ? 'Parent and child authorization completed successfully.'
                : 'Parent authorization succeeded, but child authorization did not pass.',
            'parent' => $parent,
            'child' => $child,
            'child_skipped_reason' => null,
        ];
    }

    /**
     * @return array{result: CarrierApiResult, event: CarrierApiEvent}
     */
    public function runParentAuthorization(
        CarrierAccount $account,
        ?string $validationRunId = null,
        ?string $validationCase = null,
    ): array {
        $account->loadMissing('store');
        $meta = FedExValidationScenarioCatalog::authorizationScenarios()[CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT];

        $runMeta = array_filter([
            'validation_run_id' => $validationRunId,
            'validation_case' => $validationCase,
        ]);

        $event = $this->eventLogger->start(
            store: $account->store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: (string) $meta['action'],
            account: $account,
            requestSummary: array_merge([
                'endpoint' => $this->config->oauthPath(),
                'http_method' => 'POST',
                'grant_type' => $meta['grant_type'],
                'credentials_mode' => 'integrator_parent',
            ], $runMeta),
            environment: $account->environment,
            context: new FedExValidationEventContext(
                scenarioKey: CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT,
            ),
        );

        $result = $this->parentOAuth->fetchTokenResult($account->environment, fresh: true);
        $completed = $this->eventLogger->complete($event, $result);

        if ($runMeta !== []) {
            $completed->update([
                'request_summary' => array_merge($completed->request_summary ?? [], $runMeta),
                'response_summary' => array_merge($completed->response_summary ?? [], $runMeta),
            ]);
            $completed = $completed->fresh();
        }

        return ['result' => $result, 'event' => $completed];
    }

    /**
     * @return array{result: CarrierApiResult, event: CarrierApiEvent}
     */
    public function runChildAuthorization(CarrierAccount $account): array
    {
        $account->loadMissing('store');
        $meta = FedExValidationScenarioCatalog::authorizationScenarios()[CarrierApiEvent::SCENARIO_AUTHORIZATION_CHILD];

        $event = $this->eventLogger->start(
            store: $account->store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: (string) $meta['action'],
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'http_method' => 'POST',
                'grant_type' => $meta['grant_type'],
                'credentials_mode' => 'integrator_child',
                'account_last4' => $this->accountLast4($account),
            ],
            environment: $account->environment,
            context: new FedExValidationEventContext(
                scenarioKey: CarrierApiEvent::SCENARIO_AUTHORIZATION_CHILD,
            ),
        );

        $result = $this->childOAuth->fetchTokenResult($account, fresh: true);
        $completed = $this->eventLogger->complete($event, $result);

        return ['result' => $result, 'event' => $completed];
    }

    private function accountLast4(CarrierAccount $account): ?string
    {
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null;
    }
}
