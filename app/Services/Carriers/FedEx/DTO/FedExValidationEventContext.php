<?php

namespace App\Services\Carriers\FedEx\DTO;

final class FedExValidationEventContext
{
    public function __construct(
        public readonly ?int $registrationSessionId = null,
        public readonly ?string $scenarioKey = null,
        public readonly ?string $testCaseKey = null,
        public readonly ?string $mfaMethod = null,
        public readonly ?string $labelFormat = null,
        public readonly ?int $packageCount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toEventAttributes(): array
    {
        return array_filter([
            'registration_session_id' => $this->registrationSessionId,
            'scenario_key' => $this->scenarioKey,
            'test_case_key' => $this->testCaseKey,
            'mfa_method' => $this->mfaMethod,
            'label_format' => $this->labelFormat,
            'package_count' => $this->packageCount,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
