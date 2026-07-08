<?php

namespace App\Services\Carriers\USPS\Presenters;

use App\Models\CarrierAccount;
use App\Services\Carriers\USPS\Support\USPSMerchantConnectionContext;

final class USPSMerchantStatusPresenter
{
    public function __construct(
        private readonly CarrierAccount $account,
    ) {}

    public static function for(CarrierAccount $account): self
    {
        return new self($account);
    }

    public function authorizationStatusLabel(): string
    {
        return match ($this->account->usps_authorization_status) {
            CarrierAccount::USPS_AUTH_SETUP_REQUIRED => 'Setup required',
            CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION => 'Awaiting USPS authorization',
            CarrierAccount::USPS_AUTH_VERIFYING => 'Verifying connection',
            CarrierAccount::USPS_AUTH_CONNECTED => 'Connected',
            CarrierAccount::USPS_AUTH_ACTION_REQUIRED => 'Action required',
            CarrierAccount::USPS_AUTH_REVOKED => 'Authorization revoked',
            CarrierAccount::USPS_AUTH_DISABLED => 'Disconnected',
            default => 'Setup required',
        };
    }

    public function enrollmentStatusLabel(): string
    {
        return match ($this->account->usps_enrollment_status) {
            CarrierAccount::USPS_ENROLLMENT_NOT_STARTED => 'Not started',
            CarrierAccount::USPS_ENROLLMENT_PENDING => 'Pending verification',
            CarrierAccount::USPS_ENROLLMENT_VERIFIED => 'Verified',
            CarrierAccount::USPS_ENROLLMENT_FAILED => 'Needs attention',
            default => 'Not started',
        };
    }

    public function paymentStatusLabel(): string
    {
        if ($this->account->usps_payment_verified_at !== null) {
            return 'Postage account verified '.$this->account->usps_payment_verified_at->timezone('UTC')->format('M j, Y');
        }

        return 'Not verified yet';
    }

    public function merchantSummary(): string
    {
        if ($this->account->usps_authorization_status === CarrierAccount::USPS_AUTH_DISABLED) {
            return 'This USPS connection was disconnected. Start again when you are ready to reconnect your merchant-owned account.';
        }

        if ($this->account->usps_authorization_status === CarrierAccount::USPS_AUTH_VERIFYING) {
            if (USPSMerchantConnectionContext::for($this->account)->hasOAuthAuthorizationVerified()) {
                return 'Label Provider authorization is verified with USPS. Ship enrollment and postage account verification are the next steps before rates and labels can be enabled.';
            }

            return 'Waiting for connection verification. Use Verify with USPS when platform OAuth is enabled, or continue after BmyBrand platform approval is complete.';
        }

        if ($this->account->usps_authorization_status === CarrierAccount::USPS_AUTH_CONNECTED) {
            return 'Your merchant-owned USPS account is connected. Postage is charged to your USPS payment account. BmyBrand does not pay for your labels.';
        }

        if ($this->account->usps_authorization_status === CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION) {
            return 'Authorize BmyBrand as your Label Provider in the USPS Business Portal, then return here to verify the connection. You never paste API keys or passwords into BmyBrand.';
        }

        if ($this->account->usps_authorization_status === CarrierAccount::USPS_AUTH_ACTION_REQUIRED) {
            return 'USPS needs attention on your business account before this connection can continue. Review the message below and update your USPS account if needed.';
        }

        return 'Connect your merchant-owned USPS business account. Postage stays on your USPS payment account and labels are not enabled until later phases verify authorization and payment.';
    }

    public function badgeClass(): string
    {
        return match ($this->account->usps_authorization_status) {
            CarrierAccount::USPS_AUTH_CONNECTED => 'bg-[#ECFDF5] text-[#047857]',
            CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION, CarrierAccount::USPS_AUTH_VERIFYING => 'bg-[#FEF3C7] text-[#92400E]',
            CarrierAccount::USPS_AUTH_ACTION_REQUIRED, CarrierAccount::USPS_AUTH_REVOKED => 'bg-[#FEF2F2] text-[#991B1B]',
            CarrierAccount::USPS_AUTH_DISABLED => 'bg-[#F1F5F9] text-[#64748B]',
            default => 'bg-[#EFF6FF] text-[#1D4ED8]',
        };
    }

    public function nextStepLabel(): string
    {
        return match ($this->account->usps_authorization_status) {
            CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION => 'Authorize in USPS portal',
            CarrierAccount::USPS_AUTH_VERIFYING => 'Review connection',
            CarrierAccount::USPS_AUTH_CONNECTED => 'Manage connection',
            CarrierAccount::USPS_AUTH_DISABLED => 'Connect again',
            default => 'Continue setup',
        };
    }
}
