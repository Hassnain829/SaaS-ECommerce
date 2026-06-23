<?php

namespace App\Services\Carriers\FedEx;

use App\Services\Carriers\DTO\CarrierApiResult;

class FedExIntegratorParentOAuthService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOAuthTokenService $oauthTokenService,
    ) {}

    public function fetchTokenResult(?string $environment = null, bool $fresh = false): CarrierApiResult
    {
        $environment = $this->config->environment($environment);

        if (! $this->config->isConfigured($environment)) {
            return CarrierApiResult::failure(
                message: 'FedEx platform credentials are not configured. Contact the platform administrator.',
                code: 'platform_config_missing',
                requestSummary: [
                    'endpoint' => $this->config->oauthPath(),
                    'environment' => $environment,
                    'credentials_mode' => 'integrator_parent',
                ],
            );
        }

        return $this->oauthTokenService->fetchPlatformTokenResult($environment, $fresh);
    }
}
