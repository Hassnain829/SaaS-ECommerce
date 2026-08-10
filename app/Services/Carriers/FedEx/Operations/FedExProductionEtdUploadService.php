<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\FedExTradeDocument;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Production electronic trade-document upload for international shipments.
 */
final class FedExProductionEtdUploadService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
        private readonly FedExTradeDocumentUploadService $tradeDocuments,
        private readonly FedExTradeDocumentUploadPayloadFactory $payloadFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     document: ?FedExTradeDocument,
     *     document_id: ?string,
     *     result_success: bool,
     *     merchant_message: string,
     *     prepared: array<string, mixed>
     * }
     */
    public function uploadCommercialInvoice(
        Store $store,
        CarrierAccount $account,
        UploadedFile $file,
        array $meta = [],
        ?Order $order = null,
        ?Shipment $shipment = null,
    ): array {
        $this->guard->assertAccountForOperation($store, $account, FedExOperationGuard::CAPABILITY_SHIP_LABELS);

        $originCountry = strtoupper(trim((string) ($meta['origin_country_code'] ?? '')));
        $destinationCountry = strtoupper(trim((string) ($meta['destination_country_code'] ?? '')));

        if ($originCountry === '' || ! preg_match('/^[A-Z]{2}$/', $originCountry)) {
            throw ValidationException::withMessages([
                'origin_country_code' => 'Origin country is required for electronic trade documents.',
            ]);
        }
        if ($destinationCountry === '' || ! preg_match('/^[A-Z]{2}$/', $destinationCountry)) {
            throw ValidationException::withMessages([
                'destination_country_code' => 'Destination country is required for electronic trade documents.',
            ]);
        }

        if ($order !== null) {
            abort_unless((int) $order->store_id === (int) $store->id, 404);
        }
        if ($shipment !== null) {
            abort_unless((int) $shipment->store_id === (int) $store->id, 404);
        }

        $disk = (string) config('carriers.fedex.label_storage_disk', 'local');
        $storedPath = $file->storeAs(
            sprintf('fedex/etd/%d', (int) $store->id),
            Str::uuid()->toString().'.pdf',
            $disk,
        );
        $absolute = Storage::disk($disk)->path($storedPath);

        $record = FedExTradeDocument::query()->create([
            'store_id' => $store->id,
            'order_id' => $order?->id,
            'shipment_id' => $shipment?->id,
            'carrier_account_id' => $account->id,
            'document_type' => 'COMMERCIAL_INVOICE',
            'status' => FedExTradeDocument::STATUS_PENDING,
            'origin_country_code' => $originCountry,
            'destination_country_code' => $destinationCountry,
            'storage_disk' => $disk,
            'storage_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'metadata' => [
                'mime_type' => $file->getMimeType(),
            ],
        ]);

        try {
            $preparedBuilt = $this->payloadFactory->buildDocumentUpload([
                'upload' => [
                    'mode' => 'document',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'workflow_name' => 'ETDPreShipment',
                    'absolute_path' => $absolute,
                    'filename' => pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) === 'pdf'
                        ? $file->getClientOriginalName()
                        : (pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.pdf'),
                    'origin_country_code' => $originCountry,
                    'destination_country_code' => $destinationCountry,
                ],
            ]);

            $prepared = array_merge($preparedBuilt, [
                'attachment_absolute_path' => $absolute,
                'original_filename' => (string) ($preparedBuilt['attachment']['filename'] ?? $file->getClientOriginalName()),
                'mime_type' => (string) ($preparedBuilt['attachment']['mime_type'] ?? 'application/pdf'),
                'case_key' => 'production_etd',
                'upload_mode' => 'document',
                'scenario_key' => 'production_commercial_invoice',
                'document_type' => 'COMMERCIAL_INVOICE',
                'endpoint_host' => $this->config->documentApiBaseUrl($account->environment),
                'endpoint_path' => (string) ($preparedBuilt['endpoint_path']
                    ?? $this->config->tradeDocumentsUploadDocumentPath()),
                'stored_path' => $storedPath,
                'stored_disk' => $disk,
                'request_summary' => [
                    'production_etd' => true,
                    'fedex_trade_document_id' => $record->id,
                    'order_id' => $order?->id,
                    'shipment_id' => $shipment?->id,
                    'document_environment' => $account->environment,
                ],
            ]);

            $executed = $this->tradeDocuments->executePreparedUpload(
                store: $store,
                account: $account,
                prepared: $prepared,
                allowLive: true,
            );

            $success = (bool) ($executed['result']->success ?? false);
            $documentId = $executed['returned_document_id'] ?? null;

            if (! $success || ! filled($documentId)) {
                $this->cleanupFailedUpload($record);

                return [
                    'document' => $record->fresh(),
                    'document_id' => null,
                    'result_success' => false,
                    'merchant_message' => 'We couldn\'t prepare the customs document for FedEx. Review the document and try again.',
                    'prepared' => [
                        'fedex_trade_document_id' => $record->id,
                    ],
                ];
            }

            $record->forceFill([
                'fedex_document_id' => (string) $documentId,
                'status' => FedExTradeDocument::STATUS_UPLOADED,
                'uploaded_at' => now(),
                'metadata' => array_merge((array) $record->metadata, [
                    'fedex_transaction_id' => data_get($executed['result']->responseSummary ?? [], 'fedex_transaction_id'),
                ]),
            ])->save();

            if ($order !== null) {
                $meta = $order->meta ?? [];
                $docs = (array) data_get($meta, 'fedex.trade_documents', []);
                $docs[] = [
                    'id' => $record->id,
                    'fedex_document_id' => (string) $documentId,
                    'document_type' => 'COMMERCIAL_INVOICE',
                    'uploaded_at' => now()->toIso8601String(),
                ];
                $meta['fedex']['trade_documents'] = $docs;
                $order->forceFill(['meta' => $meta])->save();
            }

            return [
                'document' => $record->fresh(),
                'document_id' => (string) $documentId,
                'result_success' => true,
                'merchant_message' => 'Commercial Invoice Ready ✓',
                'prepared' => [
                    'fedex_trade_document_id' => $record->id,
                    'document_id' => (string) $documentId,
                    'stored_path' => $storedPath,
                    'stored_disk' => $disk,
                ],
            ];
        } catch (\Throwable $e) {
            $this->cleanupFailedUpload($record);
            throw $e;
        }
    }

    /**
     * Reuse a still-valid uploaded commercial invoice for the same order/account/route context.
     */
    public function findReusableCommercialInvoice(
        Store $store,
        CarrierAccount $account,
        Order $order,
        string $originCountry,
        string $destinationCountry,
    ): ?FedExTradeDocument {
        abort_unless((int) $order->store_id === (int) $store->id, 404);

        return FedExTradeDocument::query()
            ->where('store_id', $store->id)
            ->where('order_id', $order->id)
            ->where('carrier_account_id', $account->id)
            ->where('document_type', 'COMMERCIAL_INVOICE')
            ->where('status', FedExTradeDocument::STATUS_UPLOADED)
            ->where('origin_country_code', strtoupper($originCountry))
            ->where('destination_country_code', strtoupper($destinationCountry))
            ->whereNotNull('fedex_document_id')
            ->whereNull('shipment_id')
            ->latest('id')
            ->first();
    }

    private function cleanupFailedUpload(FedExTradeDocument $record): void
    {
        if (filled($record->storage_disk) && filled($record->storage_path)) {
            try {
                Storage::disk((string) $record->storage_disk)->delete((string) $record->storage_path);
            } catch (\Throwable) {
                // best-effort cleanup
            }
        }

        $record->forceFill([
            'status' => FedExTradeDocument::STATUS_FAILED,
            'storage_path' => null,
            'metadata' => array_merge((array) $record->metadata, [
                'cleaned_up_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }
}
