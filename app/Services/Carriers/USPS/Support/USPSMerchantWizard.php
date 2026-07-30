<?php

namespace App\Services\Carriers\USPS\Support;

use App\Models\CarrierAccount;

final class USPSMerchantWizard
{
    public const STEP_REQUIREMENTS = 'requirements';

    public const STEP_ORIGIN = 'origin';

    public const STEP_IDENTIFIERS = 'identifiers';

    public const STEP_AUTHORIZATION = 'authorization';

    /**
     * @return list<string>
     */
    public static function steps(): array
    {
        return [
            self::STEP_REQUIREMENTS,
            self::STEP_ORIGIN,
            self::STEP_IDENTIFIERS,
            self::STEP_AUTHORIZATION,
        ];
    }

    public static function isValidStep(string $step): bool
    {
        return in_array($step, self::steps(), true);
    }

    public function resolveStep(CarrierAccount $account): string
    {
        $context = USPSMerchantConnectionContext::for($account);

        if (! $context->hasCompletedStep(self::STEP_ORIGIN)) {
            return self::STEP_ORIGIN;
        }

        if (! $context->hasCompletedStep(self::STEP_IDENTIFIERS)) {
            return self::STEP_IDENTIFIERS;
        }

        if ($account->usps_authorization_status === CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION) {
            return self::STEP_AUTHORIZATION;
        }

        return self::STEP_AUTHORIZATION;
    }

    public function isWizardComplete(CarrierAccount $account): bool
    {
        return in_array($account->usps_authorization_status, [
            CarrierAccount::USPS_AUTH_VERIFYING,
            CarrierAccount::USPS_AUTH_CONNECTED,
            CarrierAccount::USPS_AUTH_ACTION_REQUIRED,
            CarrierAccount::USPS_AUTH_REVOKED,
        ], true);
    }

    /**
     * @return list<array{key: string, label: string, status: string}>
     */
    public function progress(CarrierAccount $account): array
    {
        $context = USPSMerchantConnectionContext::for($account);
        $current = $this->resolveStep($account);

        return collect([
            ['key' => self::STEP_REQUIREMENTS, 'label' => 'Requirements'],
            ['key' => self::STEP_ORIGIN, 'label' => 'Ship-from location'],
            ['key' => self::STEP_IDENTIFIERS, 'label' => 'USPS account details'],
            ['key' => self::STEP_AUTHORIZATION, 'label' => 'Label Provider authorization'],
        ])->map(function (array $step) use ($context, $current, $account): array {
            $completed = match ($step['key']) {
                self::STEP_REQUIREMENTS => true,
                self::STEP_ORIGIN => $context->hasCompletedStep(self::STEP_ORIGIN),
                self::STEP_IDENTIFIERS => $context->hasCompletedStep(self::STEP_IDENTIFIERS),
                self::STEP_AUTHORIZATION => $this->isWizardComplete($account)
                    || $account->usps_authorization_status === CarrierAccount::USPS_AUTH_VERIFYING,
                default => false,
            };

            $status = $completed ? 'complete' : ($step['key'] === $current ? 'current' : 'upcoming');

            return array_merge($step, ['status' => $status]);
        })->all();
    }

    public function stepLabel(string $step): string
    {
        return match ($step) {
            self::STEP_REQUIREMENTS => 'USPS requirements',
            self::STEP_ORIGIN => 'Ship-from location',
            self::STEP_IDENTIFIERS => 'USPS account details',
            self::STEP_AUTHORIZATION => 'Authorize Label Provider',
            default => 'Setup',
        };
    }

    public function stepNumber(string $step): int
    {
        $index = array_search($step, self::steps(), true);

        return $index === false ? 1 : $index + 1;
    }
}
