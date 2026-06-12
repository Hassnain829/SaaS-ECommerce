<?php

namespace App\Services\Carriers\FedEx\DTO;

use App\Models\CarrierAccount;

final class FedExMerchantConnectionResult
{
    public const STATUS_SAVED_SETUP_REQUIRED = 'saved_setup_required';

    public const STATUS_SAVED_VERIFICATION_PENDING = 'saved_verification_pending';

    public const STATUS_CONNECTED_FOR_TESTING = 'connected_for_testing';

    public const STATUS_CARRIER_SUPPORT_REQUIRED = 'carrier_support_required';

    public const STATUS_BLOCKED_BY_CARRIER = 'blocked_by_carrier';

    public const STATUS_FAILED_SAFE = 'failed_safe';

    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly bool $accountPersisted,
        public readonly ?CarrierAccount $account = null,
        public readonly ?string $detailMessage = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public function isVerificationSuccess(): bool
    {
        return $this->status === self::STATUS_CONNECTED_FOR_TESTING;
    }

    public function requiresCarrierSupport(): bool
    {
        return in_array($this->status, [
            self::STATUS_CARRIER_SUPPORT_REQUIRED,
            self::STATUS_BLOCKED_BY_CARRIER,
        ], true);
    }

    public static function savedSetupRequired(CarrierAccount $account, string $message): self
    {
        return new self(
            status: self::STATUS_SAVED_SETUP_REQUIRED,
            message: $message,
            accountPersisted: true,
            account: $account,
        );
    }

    public static function connectedForTesting(CarrierAccount $account, string $message): self
    {
        return new self(
            status: self::STATUS_CONNECTED_FOR_TESTING,
            message: $message,
            accountPersisted: true,
            account: $account,
        );
    }

    public static function carrierSupportRequired(
        CarrierAccount $account,
        string $message,
        ?string $detailMessage = null,
        ?string $errorCode = null,
    ): self {
        return new self(
            status: self::STATUS_CARRIER_SUPPORT_REQUIRED,
            message: $message,
            accountPersisted: true,
            account: $account,
            detailMessage: $detailMessage,
            errorCode: $errorCode,
        );
    }

    public static function failedSafe(
        CarrierAccount $account,
        string $message,
        ?string $detailMessage = null,
        ?string $errorCode = null,
    ): self {
        return new self(
            status: self::STATUS_FAILED_SAFE,
            message: $message,
            accountPersisted: true,
            account: $account,
            detailMessage: $detailMessage,
            errorCode: $errorCode,
        );
    }
}
