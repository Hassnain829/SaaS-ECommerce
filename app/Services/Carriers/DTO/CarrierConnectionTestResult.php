<?php

namespace App\Services\Carriers\DTO;

final class CarrierConnectionTestResult
{
    /**
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, string>  $steps
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $errorCode = null,
        public readonly array $capabilities = [],
        public readonly bool $registered = false,
        public readonly array $steps = [],
        public readonly ?string $detailMessage = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, string>  $steps
     */
    public static function connected(
        string $message,
        array $capabilities = [],
        bool $registered = false,
        array $steps = [],
    ): self {
        return new self(
            success: true,
            message: $message,
            capabilities: $capabilities,
            registered: $registered,
            steps: $steps,
        );
    }

    /**
     * @param  array<string, string>  $steps
     */
    public static function failed(
        string $message,
        ?string $errorCode = null,
        array $steps = [],
        ?string $detailMessage = null,
    ): self {
        return new self(
            success: false,
            message: $message,
            errorCode: $errorCode,
            steps: $steps,
            detailMessage: $detailMessage,
        );
    }

    public static function platformUnavailable(string $message): self
    {
        return new self(
            success: false,
            message: $message,
            errorCode: 'platform_config_missing',
        );
    }
}
