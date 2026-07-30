<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipValidationService;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService;

/**
 * Operator orchestration for IntegratorUS09 ETD Image / Document flows.
 *
 * Uploads and ships are sandbox operator actions; live network I/O is gated inside
 * the underlying upload/ship services (allowLive / sandbox label generation).
 */
class FedExUs09OperatorService
{
    public function __construct(
        private readonly FedExTradeDocumentUploadService $uploadService,
        private readonly FedExShipValidationService $shipValidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $uploadOverrides
     * @return array<string, mixed>
     */
    public function uploadLetterhead(Store $store, CarrierAccount $account, array $uploadOverrides = []): array
    {
        $prepared = $this->uploadService->prepareImageUpload('letterhead', $uploadOverrides);

        return $this->uploadService->executePreparedUpload($store, $account, $prepared, allowLive: true);
    }

    /**
     * @param  array<string, mixed>  $uploadOverrides
     * @return array<string, mixed>
     */
    public function uploadSignature(Store $store, CarrierAccount $account, array $uploadOverrides = []): array
    {
        $prepared = $this->uploadService->prepareImageUpload('signature', $uploadOverrides);

        return $this->uploadService->executePreparedUpload($store, $account, $prepared, allowLive: true);
    }

    /**
     * @param  array<string, mixed>  $uploadOverrides
     * @return array<string, mixed>
     */
    public function uploadDocument(Store $store, CarrierAccount $account, array $uploadOverrides = []): array
    {
        $prepared = $this->uploadService->prepareDocumentUpload($uploadOverrides);

        return $this->uploadService->executePreparedUpload($store, $account, $prepared, allowLive: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function createImageShipment(Store $store, CarrierAccount $account, ?User $actor = null): array
    {
        return $this->shipValidationService->createUs09SandboxLabel(
            store: $store,
            account: $account,
            testCaseKey: 'IntegratorUS09_IMAGE',
            actor: $actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createDocumentShipment(Store $store, CarrierAccount $account, ?User $actor = null): array
    {
        return $this->shipValidationService->createUs09SandboxLabel(
            store: $store,
            account: $account,
            testCaseKey: 'IntegratorUS09_DOCUMENT',
            actor: $actor,
        );
    }
}
