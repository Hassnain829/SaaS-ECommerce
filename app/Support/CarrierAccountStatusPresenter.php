<?php

namespace App\Support;

use App\Models\CarrierAccount;

final class CarrierAccountStatusPresenter
{
    public function __construct(
        private readonly CarrierAccount $account,
    ) {
    }

    public static function for(CarrierAccount $account): self
    {
        return new self($account);
    }

    public function ownershipLabel(): string
    {
        return match ($this->account->ownership_mode) {
            CarrierAccount::OWNERSHIP_PLATFORM_TESTING => 'Platform testing connection',
            CarrierAccount::OWNERSHIP_MERCHANT_OWNED => 'Merchant-owned account',
            CarrierAccount::OWNERSHIP_MANUAL => 'Manual/local delivery',
            CarrierAccount::OWNERSHIP_EXTERNAL_MANAGED => 'External managed account',
            default => 'Carrier account',
        };
    }

    public function connectionStatusLabel(): string
    {
        if ($this->account->isManualProvider()) {
            return match ($this->account->status) {
                CarrierAccount::STATUS_ENABLED => 'Enabled for manual delivery',
                CarrierAccount::STATUS_DISABLED => 'Disabled',
                default => 'Setup required',
            };
        }

        if ($this->account->isBlockedByFedEx()) {
            return 'Carrier support required';
        }

        if ($this->account->isSandboxPlatformFallback()) {
            return 'Connected for testing';
        }

        if ($this->account->isPlatformTesting()) {
            return match ($this->account->connection_status) {
                CarrierAccount::CONNECTION_CONNECTED => 'Connected for testing',
                CarrierAccount::CONNECTION_FAILED => 'Setup required',
                CarrierAccount::CONNECTION_SETUP_REQUIRED, CarrierAccount::CONNECTION_NOT_CONNECTED => 'Setup required',
                default => 'Setup required',
            };
        }

        return match ($this->account->connection_status) {
            CarrierAccount::CONNECTION_CONNECTED => 'Connected for rates',
            CarrierAccount::CONNECTION_SETUP_REQUIRED, CarrierAccount::CONNECTION_NOT_CONNECTED => 'Setup required',
            CarrierAccount::CONNECTION_PENDING_VALIDATION => 'Pending validation',
            CarrierAccount::CONNECTION_FAILED => 'Setup required',
            CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX => 'Carrier support required',
            CarrierAccount::CONNECTION_SANDBOX_PLATFORM_FALLBACK => 'Connected for testing',
            CarrierAccount::CONNECTION_DISABLED => 'Disabled',
            default => 'Setup required',
        };
    }

    public function merchantStatusLabel(): string
    {
        if ($this->account->isBlockedByFedEx()) {
            return 'FedEx setup is blocked by carrier account validation. Contact FedEx support or use manual delivery for now.';
        }

        if ($this->account->isPlatformTesting() && $this->account->isConnected()) {
            return 'This carrier account uses platform testing credentials. It is not your merchant-owned carrier account.';
        }

        if ($this->account->isManualProvider()) {
            return 'Use this account for manual tracking and local delivery workflows.';
        }

        if ($this->account->isConnected() && $this->account->isMerchantOwned()) {
            return 'Your merchant-owned carrier account is connected.';
        }

        return 'Finish carrier setup to use this account.';
    }

    /**
     * @return list<string>
     */
    public function merchantCapabilityLabels(): array
    {
        $labels = [];

        if ($this->account->supportsRates()) {
            $labels[] = $this->account->isPlatformTesting()
                ? 'Rates: testing only'
                : 'Rates: enabled';
        } else {
            $labels[] = 'Rates: not enabled';
        }

        $labels[] = $this->account->supportsLabels()
            ? 'Labels: enabled'
            : 'Labels not enabled';

        $labels[] = $this->account->supportsTracking()
            ? 'Tracking: enabled'
            : 'Tracking not enabled';

        $labels[] = $this->account->supportsPickup()
            ? 'Pickup: enabled'
            : 'Pickup not enabled';

        return $labels;
    }

    public function nextActionLabel(): string
    {
        if ($this->account->isManualProvider()) {
            return 'Manage manual delivery';
        }

        if ($this->account->isBlockedByFedEx()) {
            return 'Contact carrier support';
        }

        if ($this->account->connection_status === CarrierAccount::CONNECTION_CONNECTED) {
            return 'Test connection';
        }

        return 'Finish setup';
    }

    public function badgeClass(): string
    {
        if ($this->account->isBlockedByFedEx() || $this->account->connection_status === CarrierAccount::CONNECTION_FAILED) {
            return 'bg-[#FEF2F2] text-[#991B1B]';
        }

        if ($this->account->isSandboxPlatformFallback() || $this->account->isPlatformTesting()) {
            return 'bg-[#FFF7ED] text-[#C2410C]';
        }

        if ($this->account->isConnected() || ($this->account->isManualProvider() && $this->account->status === CarrierAccount::STATUS_ENABLED)) {
            return 'bg-[#ECFDF5] text-[#047857]';
        }

        if ($this->account->connection_status === CarrierAccount::CONNECTION_DISABLED || $this->account->status === CarrierAccount::STATUS_DISABLED) {
            return 'bg-[#F1F5F9] text-[#64748B]';
        }

        return 'bg-[#FEF3C7] text-[#92400E]';
    }
}
