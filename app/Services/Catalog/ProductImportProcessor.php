<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\ProductVariant;
use App\Support\Catalog\ProductImportMerchantMessages;
use App\Support\Catalog\ProductImportRowPayloadSanitizer;
use App\Support\Catalog\SpreadsheetValueNormalizer;
use App\Models\Store;
use App\Support\Catalog\ProductImportQueue;
use App\Support\StockMovementRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProductImportProcessor
{
    public function __construct(
        private readonly ProductImportSpreadsheetReader $reader,
        private readonly ProductCatalogImageDownloader $imageDownloader,
    ) {}

    private function maxRows(): int
    {
        return max(1000, min(500000, (int) config('product_import.max_rows', 100000)));
    }

    public function run(ProductImport $import): void
    {
        $import->loadMissing(['store']);
        $import->refresh();

        if ($import->status === ProductImport::STATUS_COMPLETED) {
            return;
        }

        if ($import->status === ProductImport::STATUS_PROCESSING) {
            $started = $import->started_at;
            $maxProc = max(1, (int) config('product_import.stale_processing_minutes', 45));
            if ($started && $started->lt(now()->subMinutes($maxProc))) {
                $this->failImport($import, 'A previous import run did not finish (timeout or worker crash). This attempt was aborted; please start a new import.');
            }

            return;
        }

        if ($import->status !== ProductImport::STATUS_QUEUED) {
            return;
        }

        Log::channel('import')->info('product_import_processor_accepting_job', array_merge([
            'import_id' => $import->id,
            'store_id' => $import->store_id,
        ], ProductImportQueue::diagnostics()));

        $store = $import->store;
        $mapping = $import->column_mapping ?? [];
        $headers = $import->headers ?? [];
        $customMappings = self::normalizeCustomMappings($import->custom_field_mappings ?? []);

        if ($store === null || $headers === [] || $mapping === []) {
            $this->failImport($import, 'Invalid import configuration.');

            return;
        }

        $path = Storage::disk($import->stored_disk)->path($import->stored_path);
        if (! is_file($path)) {
            $this->failImport($import, 'Import file is missing from storage.');

            return;
        }

        $maxRows = $this->maxRows();
        $chunkSize = max(50, min(2000, (int) config('product_import.chunk_size', 300)));
        $flushEvery = max(1, (int) config('product_import.progress_flush_every', 25));

        $freshStart = (int) ($import->last_processed_row ?? 0) === 0;
        if ($freshStart) {
            ProductImportRow::query()->where('product_import_id', $import->id)->delete();
        }
        $imageSummaryReset = $freshStart ? [
            'total_images' => 0,
            'processed_images' => 0,
            'failed_images' => 0,
        ] : [];

        $importState = is_array($import->import_state) ? $import->import_state : [];
        /** @var array<string, true> $seenSkuInFile */
        $seenSkuInFile = [];
        foreach (($importState['seen_sku_keys'] ?? []) as $k) {
            if (is_string($k) && $k !== '') {
                $seenSkuInFile[$k] = true;
            }
        }
        /** @var array<string, true> $assignedVariantSkusLower */
        $assignedVariantSkusLower = [];
        foreach (($importState['assigned_variant_sku_lower'] ?? []) as $k) {
            if (is_string($k) && $k !== '') {
                $assignedVariantSkusLower[$k] = true;
            }
        }

        $existingSummary = $import->result_summary ?? [];
        /** @var list<array{row:int, message:string}> $failures */
        $failures = [];
        foreach (($existingSummary['failures'] ?? []) as $f) {
            if (is_array($f) && isset($f['row'], $f['message']) && count($failures) < 200) {
                $failures[] = [
                    'row' => (int) $f['row'],
                    'message' => ProductImportMerchantMessages::truncateForStorage((string) $f['message'], 1200),
                ];
            }
        }

        $prog0 = is_array($existingSummary['progress'] ?? null) ? $existingSummary['progress'] : [];
        $created = (int) ($prog0['created'] ?? 0);
        $updated = (int) ($prog0['updated'] ?? 0);
        $skipped = (int) ($prog0['skipped'] ?? 0);
        $failed = (int) ($prog0['failed'] ?? 0);
        $warningsCount = (int) ($existingSummary['warnings_count'] ?? 0);

        $totalRows = (int) ($import->total_rows ?? 0);
        if ($totalRows < 1) {
            $totalRows = min($this->reader->countDataRows($path, $import->file_extension), $maxRows);
        }
        if ($totalRows < 1) {
            $this->failImport($import, 'Import file contains no data rows.');

            return;
        }
        if ($totalRows > $maxRows) {
            $totalRows = $maxRows;
        }

        $totalChunks = max(1, (int) ceil($totalRows / max(1, $chunkSize)));
        $startedAt = microtime(true);
        $rowIndex = 0;
        $chunkIndex = 0;

        $import->update([
            'status' => ProductImport::STATUS_PROCESSING,
            'started_at' => $import->started_at ?? now(),
            'failure_message' => null,
            'total_rows' => $totalRows,
            'result_summary' => array_merge($existingSummary, $imageSummaryReset, [
                'warnings_count' => $warningsCount,
                'failures' => $failures,
                'total_products' => $totalRows,
                'processed_products' => 0,
                'progress' => $this->buildProgressSummary(
                    $totalRows,
                    (int) ($import->last_processed_row ?? 0),
                    $created,
                    $updated,
                    $failed,
                    $skipped,
                    0,
                    $totalChunks,
                    $startedAt,
                    $warningsCount,
                    'processing',
                ),
            ]),
        ]);

        $taxonomyCache = new ProductImportTaxonomyCache($store, $import->created_by);

        try {
            $stoppedCap = false;
            $this->reader->eachDataRowChunk($path, $import->file_extension, $chunkSize, function (array $chunkRows) use (
                $import,
                $store,
                $headers,
                $mapping,
                $customMappings,
                $taxonomyCache,
                &$assignedVariantSkusLower,
                &$seenSkuInFile,
                &$created,
                &$updated,
                &$skipped,
                &$failed,
                &$failures,
                &$rowIndex,
                &$chunkIndex,
                $flushEvery,
                $totalRows,
                $totalChunks,
                $startedAt,
                &$warningsCount,
                $maxRows,
                &$stoppedCap
            ): bool {
                $chunkIndex++;
                $import->refresh();
                $skipUntil = (int) ($import->last_processed_row ?? 0);

                /** @var list<array{0:int, 1:list<string>}> $chunkMatrix */
                $chunkMatrix = [];
                foreach ($chunkRows as $cells) {
                    $rowIndex++;
                    if ($rowIndex > $maxRows) {
                        $skipped++;
                        $stoppedCap = true;

                        continue;
                    }
                    $chunkMatrix[] = [$rowIndex, $cells];
                }

                if ($chunkMatrix === []) {
                    return ! $stoppedCap;
                }

                $now = now();
                $rowsToInsert = [];
                foreach ($chunkMatrix as [$rn, $cells]) {
                    $slim = ProductImportRowPayloadSanitizer::slimForInsert($headers, $cells, $mapping, $customMappings);
                    $encoded = json_encode($slim['payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    if ($encoded === false) {
                        $encoded = '{}';
                    }
                    $rowsToInsert[] = [
                        'product_import_id' => $import->id,
                        'row_number' => $rn,
                        'status' => ProductImportRow::STATUS_PENDING,
                        'error_message' => null,
                        'payload' => $encoded,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $insertBatch = max(20, min(200, (int) config('product_import.row_payload_insert_batch_size', 80)));
                foreach (array_chunk($rowsToInsert, $insertBatch) as $batch) {
                    DB::table('product_import_rows')->insertOrIgnore($batch);
                }
                unset($rowsToInsert, $batch);

                $lastRnThisChunk = $skipUntil;
                foreach ($chunkMatrix as [$rn, $cells]) {
                    if ($rn <= $skipUntil) {
                        continue;
                    }
                    if ($rn > $maxRows) {
                        $this->markImportRow($import, $rn, ProductImportRow::STATUS_SKIPPED, 'Row skipped (catalog row cap).');
                        $lastRnThisChunk = max($lastRnThisChunk, $rn);
                        if ($rn % $flushEvery === 0) {
                            $this->persistImportCheckpoint(
                                $import,
                                $lastRnThisChunk,
                                $created,
                                $updated,
                                $failed,
                                $skipped,
                                $chunkIndex,
                                $totalChunks,
                                $totalRows,
                                $startedAt,
                                $warningsCount,
                                $failures,
                                $seenSkuInFile,
                                $assignedVariantSkusLower
                            );
                        }

                        continue;
                    }

                    $this->processImportDataRow(
                        $import,
                        $store,
                        $headers,
                        $mapping,
                        $customMappings,
                        $taxonomyCache,
                        $assignedVariantSkusLower,
                        $seenSkuInFile,
                        $rn,
                        $cells,
                        $created,
                        $updated,
                        $failed,
                        $failures,
                        $warningsCount
                    );

                    $lastRnThisChunk = max($lastRnThisChunk, $rn);
                    if ($rn % $flushEvery === 0 || $rn === $totalRows) {
                        $this->persistImportCheckpoint(
                            $import,
                            $lastRnThisChunk,
                            $created,
                            $updated,
                            $failed,
                            $skipped,
                            $chunkIndex,
                            $totalChunks,
                            $totalRows,
                            $startedAt,
                            $warningsCount,
                            $failures,
                            $seenSkuInFile,
                            $assignedVariantSkusLower
                        );
                    }
                }

                $this->persistImportCheckpoint(
                    $import,
                    $lastRnThisChunk,
                    $created,
                    $updated,
                    $failed,
                    $skipped,
                    $chunkIndex,
                    $totalChunks,
                    $totalRows,
                    $startedAt,
                    $warningsCount,
                    $failures,
                    $seenSkuInFile,
                    $assignedVariantSkusLower
                );

                unset($chunkMatrix);

                return ! $stoppedCap;
            });

            $processedCap = min($rowIndex, $maxRows);
            $import->refresh();
            $completedSummary = array_merge(is_array($import->result_summary) ? $import->result_summary : [], [
                'processed_rows' => $processedCap,
                'total_processed' => $processedCap,
                'total_rows' => $totalRows,
                'total_rows_estimated' => $totalRows,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'failures' => ProductImportMerchantMessages::truncateFailureList($failures),
                'warnings_count' => $warningsCount,
                'partial_success' => $failed > 0,
                'merchant_summary' => $failed > 0
                    ? 'Your import finished. Most rows were added or updated; a few rows need changes before they can be imported.'
                    : 'Your import finished. Every row in your file was processed.',
                'total_products' => $totalRows,
                'processed_products' => $created + $updated + $skipped + $failed,
                'progress' => $this->buildProgressSummary(
                    $totalRows,
                    $processedCap,
                    $created,
                    $updated,
                    $failed,
                    $skipped,
                    $chunkIndex,
                    $totalChunks,
                    $startedAt,
                    $warningsCount,
                    'completed',
                ),
            ]);
            $import->update([
                'status' => ProductImport::STATUS_COMPLETED,
                'completed_at' => now(),
                'last_processed_row' => $processedCap,
                'import_state' => [
                    'seen_sku_keys' => array_keys($seenSkuInFile),
                    'assigned_variant_sku_lower' => array_keys($assignedVariantSkusLower),
                ],
                'result_summary' => $completedSummary,
            ]);
        } catch (\Throwable $e) {
            Log::channel('import')->error('product_import_failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);
            $this->failImport($import, $e->getMessage());
        }
    }

    private function failImport(ProductImport $import, string $message): void
    {
        Log::channel('import')->error('product_import_fatal', [
            'import_id' => $import->id,
            'message' => $message,
        ]);
        $safe = ProductImportMerchantMessages::truncateForStorage(
            ProductImportMerchantMessages::humanizeRowError($message),
            2500
        );
        $import->update([
            'status' => ProductImport::STATUS_FAILED,
            'failure_message' => $safe,
            'completed_at' => now(),
            'result_summary' => array_merge($import->result_summary ?? [], [
                'fatal_error' => true,
            ]),
        ]);
    }

    /**
     * @param  array<string, true>  $seenSkuInFile
     * @param  array<string, true>  $assignedVariantSkusLower
     * @param  list<array{row:int, message:string}>  $failures
     */
    private function persistImportCheckpoint(
        ProductImport $import,
        int $lastRowNumber,
        int $created,
        int $updated,
        int $failed,
        int $skipped,
        int $chunkIndex,
        int $totalChunks,
        int $totalRows,
        float $startedAt,
        int $warningsCount,
        array $failures,
        array $seenSkuInFile,
        array $assignedVariantSkusLower,
    ): void {
        $import->refresh();
        $import->update([
            'last_processed_row' => $lastRowNumber,
            'import_state' => [
                'seen_sku_keys' => array_keys($seenSkuInFile),
                'assigned_variant_sku_lower' => array_keys($assignedVariantSkusLower),
            ],
            'result_summary' => array_merge(is_array($import->result_summary) ? $import->result_summary : [], [
                'warnings_count' => $warningsCount,
                'failures' => ProductImportMerchantMessages::truncateFailureList($failures),
                'total_products' => $totalRows,
                'processed_products' => $created + $updated + $skipped + $failed,
                'progress' => $this->buildProgressSummary(
                    $totalRows,
                    $lastRowNumber,
                    $created,
                    $updated,
                    $failed,
                    $skipped,
                    $chunkIndex,
                    $totalChunks,
                    $startedAt,
                    $warningsCount,
                    'processing',
                ),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProgressSummary(
        int $totalRows,
        int $processedRows,
        int $created,
        int $updated,
        int $failed,
        int $skipped,
        int $currentChunk,
        int $totalChunks,
        float $startedAt,
        int $warningsCount,
        string $phase,
    ): array {
        $pct = $totalRows > 0 ? round(100 * min($processedRows, $totalRows) / $totalRows, 1) : 0.0;
        $elapsed = microtime(true) - $startedAt;
        $eta = null;
        if ($phase === 'processing' && $processedRows > 0 && $totalRows > 0) {
            $rate = $processedRows / max($elapsed, 0.001);
            $eta = (int) round(max(0, $totalRows - $processedRows) / max($rate, 0.0001));
        }

        return [
            'phase' => $phase,
            'processed_rows' => $processedRows,
            'total_rows' => $totalRows,
            'total_rows_estimated' => $totalRows,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'current_chunk' => $currentChunk,
            'total_chunks' => $totalChunks,
            'progress_percentage' => $pct,
            'eta_seconds' => $eta,
            'warnings_count' => $warningsCount,
        ];
    }

    private function markImportRow(ProductImport $import, int $rowNumber, string $status, ?string $error): void
    {
        ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('row_number', $rowNumber)
            ->update([
                'status' => $status,
                'error_message' => $error !== null
                    ? ProductImportMerchantMessages::truncateForStorage($error, 4000)
                    : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, true>  $assignedVariantSkusLower
     * @param  array<string, true>  $seenSkuInFile
     * @param  list<array{row:int, message:string}>  $failures
     */
    private function processImportDataRow(
        ProductImport $import,
        Store $store,
        array $headers,
        array $mapping,
        array $customMappings,
        ProductImportTaxonomyCache $taxonomyCache,
        array &$assignedVariantSkusLower,
        array &$seenSkuInFile,
        int $dataRowNumber,
        array $cells,
        int &$created,
        int &$updated,
        int &$failed,
        array &$failures,
        int &$warningsCount,
    ): void {
        $excelRow = $dataRowNumber + 1;
        $row = $this->cellsToKeyedRow($headers, $cells);
        $previewErrors = ProductImportRowValidator::validateMappedRow($row, $mapping, $customMappings);
        if ($previewErrors !== []) {
            $failed++;
            $merchantMsg = ProductImportMerchantMessages::humanizeRowErrors($previewErrors);
            if (count($failures) < 200) {
                $failures[] = ['row' => $excelRow, 'message' => $merchantMsg];
            }
            $this->markImportRow($import, $dataRowNumber, ProductImportRow::STATUS_FAILED, $merchantMsg);

            return;
        }

        $fields = $this->extractMappedFields($row, $mapping);
        $sku = trim((string) ($fields[ProductImportField::SKU] ?? ''));
        $skuKey = mb_strtolower($sku);
        if (isset($seenSkuInFile[$skuKey])) {
            $failed++;
            $dupMsg = ProductImportMerchantMessages::humanizeRowError('Duplicate SKU in import file.');
            if (count($failures) < 200) {
                $failures[] = ['row' => $excelRow, 'message' => $dupMsg];
            }
            $this->markImportRow($import, $dataRowNumber, ProductImportRow::STATUS_FAILED, $dupMsg);

            return;
        }
        $seenSkuInFile[$skuKey] = true;

        $extras = $this->collectUnmappedExtras($row, $headers, $mapping, $customMappings);

        $pendingImages = null;
        try {
            DB::transaction(function () use (
                $import,
                $store,
                $fields,
                $extras,
                $row,
                $customMappings,
                $taxonomyCache,
                &$assignedVariantSkusLower,
                &$pendingImages,
            ): void {
                $pendingImages = $this->persistProductCatalogRow(
                    $import,
                    $store,
                    $fields,
                    $extras,
                    $row,
                    $customMappings,
                    $taxonomyCache,
                    $assignedVariantSkusLower
                );
            });
        } catch (\Throwable $e) {
            Log::channel('import')->warning('product_import_row_failed', [
                'import_id' => $import->id,
                'row' => $excelRow,
                'error' => $e->getMessage(),
            ]);
            $failed++;
            $rowErr = ProductImportMerchantMessages::humanizeException($e);
            if (count($failures) < 200) {
                $failures[] = ['row' => $excelRow, 'message' => $rowErr];
            }
            $this->markImportRow($import, $dataRowNumber, ProductImportRow::STATUS_FAILED, $rowErr);

            return;
        }

        if ($pendingImages === null) {
            return;
        }

        if ($pendingImages['action'] === 'created') {
            $created++;
        } else {
            $updated++;
        }

        $urlsRaw = trim((string) ($pendingImages['urls'] ?? ''));
        if ($urlsRaw !== '') {
            $urls = $this->splitDelimited($urlsRaw);
            if ($urls === []) {
                $warningsCount++;
            } else {
                $this->queueOrSyncCatalogImages(
                    $pendingImages['product']->fresh(),
                    $store,
                    $urls,
                    $import->created_by,
                    $import
                );
            }
        }

        $this->markImportRow($import, $dataRowNumber, ProductImportRow::STATUS_PROCESSED, null);
    }

    /**
     * @param  list<string>  $urls
     */
    private function queueOrSyncCatalogImages(Product $product, Store $store, array $urls, ?int $userId, ProductImport $import): void
    {
        if ($urls === []) {
            return;
        }
        if (config('product_import.async_image_processing', true)) {
            $n = $this->imageDownloader->enqueueRemoteUrlsForImport($import, $product, $store, $urls, $userId);
            if ($n > 0) {
                ProductImportMediaProgress::adjust((int) $import->id, $n, 0, 0);
            }

            return;
        }

        $n = $this->imageDownloader->importUrls($product, $store, $urls, $userId);
        if ($n > 0) {
            ProductImportMediaProgress::adjust((int) $import->id, $n, $n, 0);
        }
    }

    /**
     * Re-process only rows that previously failed, using stored row payloads (same import, same store).
     * Does not re-read the spreadsheet; safe when the original file was removed after import.
     *
     * @return array{ok: bool, retried: int, failed_remaining: int, message?: string}
     */
    public function retryFailedRows(ProductImport $import): array
    {
        $import->loadMissing(['store']);
        $import->refresh();
        $store = $import->store;
        if ($store === null) {
            return ['ok' => false, 'retried' => 0, 'failed_remaining' => 0, 'message' => 'This import is not linked to a store.'];
        }

        $headers = $import->headers ?? [];
        $mapping = $import->column_mapping ?? [];
        $customMappings = self::normalizeCustomMappings($import->custom_field_mappings ?? []);
        if ($headers === [] || $mapping === []) {
            return ['ok' => false, 'retried' => 0, 'failed_remaining' => 0, 'message' => 'This import is missing column setup. Start a new import from your file.'];
        }

        $failedRows = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->orderBy('row_number')
            ->get();

        if ($failedRows->isEmpty()) {
            return ['ok' => true, 'retried' => 0, 'failed_remaining' => 0, 'message' => 'There are no failed rows to retry.'];
        }

        $importState = is_array($import->import_state) ? $import->import_state : [];
        /** @var array<string, true> $seenSkuInFile */
        $seenSkuInFile = [];
        foreach (($importState['seen_sku_keys'] ?? []) as $k) {
            if (is_string($k) && $k !== '') {
                $seenSkuInFile[$k] = true;
            }
        }
        /** @var array<string, true> $assignedVariantSkusLower */
        $assignedVariantSkusLower = [];
        foreach (($importState['assigned_variant_sku_lower'] ?? []) as $k) {
            if (is_string($k) && $k !== '') {
                $assignedVariantSkusLower[$k] = true;
            }
        }

        $taxonomyCache = new ProductImportTaxonomyCache($store, $import->created_by);
        $rs = $import->result_summary ?? [];
        $created = (int) ($rs['created'] ?? 0);
        $updated = (int) ($rs['updated'] ?? 0);
        $skipped = (int) ($rs['skipped'] ?? 0);
        $warningsCount = (int) ($rs['warnings_count'] ?? 0);
        $scratchFailed = 0;
        /** @var list<array{row:int, message:string}> $scratchFailures */
        $scratchFailures = [];

        foreach ($failedRows as $fr) {
            $payload = $fr->payload;
            $cells = is_array($payload) ? ($payload['cells'] ?? null) : null;
            if (! is_array($cells)) {
                $bad = ProductImportMerchantMessages::humanizeRowError('This row could not be retried because saved row data was incomplete.');
                $this->markImportRow($import, (int) $fr->row_number, ProductImportRow::STATUS_FAILED, $bad);

                continue;
            }
            $rn = (int) $fr->row_number;
            $this->processImportDataRow(
                $import,
                $store,
                $headers,
                $mapping,
                $customMappings,
                $taxonomyCache,
                $assignedVariantSkusLower,
                $seenSkuInFile,
                $rn,
                $cells,
                $created,
                $updated,
                $scratchFailed,
                $scratchFailures,
                $warningsCount
            );
        }

        $failedRemaining = (int) ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->count();

        /** @var list<array{row:int, message:string}> $failuresOut */
        $failuresOut = [];
        foreach (
            ProductImportRow::query()
                ->where('product_import_id', $import->id)
                ->where('status', ProductImportRow::STATUS_FAILED)
                ->orderBy('row_number')
                ->limit(200)
                ->get() as $r
        ) {
            $failuresOut[] = [
                'row' => (int) $r->row_number + 1,
                'message' => ProductImportMerchantMessages::humanizeRowError((string) ($r->error_message ?? '')),
            ];
        }

        $import->update([
            'import_state' => [
                'seen_sku_keys' => array_keys($seenSkuInFile),
                'assigned_variant_sku_lower' => array_keys($assignedVariantSkusLower),
            ],
            'result_summary' => array_merge($rs, [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failedRemaining,
                'failures' => ProductImportMerchantMessages::truncateFailureList($failuresOut),
                'warnings_count' => $warningsCount,
                'partial_success' => $failedRemaining > 0,
                'merchant_note' => $failedRemaining === 0
                    ? 'All previously failed rows were imported successfully.'
                    : 'Some rows still need attention after retry. Review the list below or fix your file and run a new import.',
            ]),
        ]);

        return [
            'ok' => true,
            'retried' => $failedRows->count(),
            'failed_remaining' => $failedRemaining,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $extras
     * @param  array<string, string>  $row
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @param  array<string, true>  $assignedVariantSkusLower
     * @return array{action: string, product: Product, urls: string}
     */
    private function persistProductCatalogRow(
        ProductImport $import,
        \App\Models\Store $store,
        array $fields,
        array $extras,
        array $row,
        array $customMappings,
        ProductImportTaxonomyCache $taxonomyCache,
        array &$assignedVariantSkusLower,
    ): array {
        $name = trim((string) ($fields[ProductImportField::PRODUCT_NAME] ?? ''));
        $productSku = trim((string) ($fields[ProductImportField::SKU] ?? ''));
        $variantSkuDesired = trim((string) ($fields[ProductImportField::VARIANT_SKU] ?? ''));
        $variantSku = $variantSkuDesired !== '' ? $variantSkuDesired : $productSku;
        $variantSku = $this->ensureUniqueVariantSku($variantSku, $store->id, $assignedVariantSkusLower);

        [$productCustom, $variantCustom] = $this->extractCustomFieldValues($row, $customMappings);

        $basePrice = SpreadsheetValueNormalizer::normalizeDecimal($fields[ProductImportField::BASE_PRICE] ?? '') ?? 0.0;
        $stock = SpreadsheetValueNormalizer::normalizeInteger($fields[ProductImportField::STOCK] ?? '') ?? 0;
        $stockAlert = SpreadsheetValueNormalizer::normalizeInteger($fields[ProductImportField::LOW_STOCK_THRESHOLD] ?? '') ?? 0;
        $description = trim((string) ($fields[ProductImportField::DESCRIPTION] ?? ''));
        $shortDesc = trim((string) ($fields[ProductImportField::SHORT_DESCRIPTION] ?? ''));
        $productType = $this->normalizeProductType(trim((string) ($fields[ProductImportField::PRODUCT_TYPE] ?? '')));
        $status = $this->parseStatus($fields[ProductImportField::STATUS] ?? '', $fields[ProductImportField::VISIBILITY] ?? '');

        $catalogMeta = array_filter([
            'barcode' => trim((string) ($fields[ProductImportField::BARCODE] ?? '')) ?: null,
            'compare_at_price' => SpreadsheetValueNormalizer::normalizeDecimal($fields[ProductImportField::COMPARE_AT_PRICE] ?? ''),
            'cost_price' => SpreadsheetValueNormalizer::normalizeDecimal($fields[ProductImportField::COST_PRICE] ?? ''),
            'short_description' => $shortDesc !== '' ? $shortDesc : null,
            'weight' => trim((string) ($fields[ProductImportField::WEIGHT] ?? '')) ?: null,
            'length' => trim((string) ($fields[ProductImportField::LENGTH] ?? '')) ?: null,
            'width' => trim((string) ($fields[ProductImportField::WIDTH] ?? '')) ?: null,
            'height' => trim((string) ($fields[ProductImportField::HEIGHT] ?? '')) ?: null,
        ], static fn ($v) => $v !== null && $v !== '');

        $variantOptions = array_filter([
            'option_1' => trim((string) ($fields[ProductImportField::VARIANT_OPTION_1] ?? '')),
            'option_2' => trim((string) ($fields[ProductImportField::VARIANT_OPTION_2] ?? '')),
        ]);

        $product = Product::query()
            ->where('store_id', $store->id)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($productSku)])
            ->first();

        $performedBy = $import->created_by;
        $importId = (int) $import->id;

        if (! $product) {
            $meta = $this->mergeMetaLayer([], $extras, $catalogMeta, $variantOptions, $stockAlert, $productCustom);
            $slug = $this->uniqueProductSlug($store->id, $name);

            $brandName = trim((string) ($fields[ProductImportField::BRAND] ?? ''));
            $product = Product::query()->create([
                'store_id' => $store->id,
                'brand_id' => $brandName !== '' ? $taxonomyCache->brandId($brandName) : null,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'base_price' => $basePrice,
                'sku' => $productSku,
                'product_type' => $productType,
                'status' => $status,
                'meta' => $meta,
            ]);

            $this->syncTaxonomy($product, $fields, $taxonomyCache);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $variantSku,
                'price' => $basePrice,
                'stock' => $stock,
                'stock_alert' => max(0, $stockAlert),
            ]);
            $variant->options()->sync([]);

            $this->mergeVariantCustomFields($variant, $variantCustom);

            StockMovementRecorder::recordImport(
                $store,
                $product,
                $variant,
                null,
                $stock,
                $performedBy,
                $importId,
                'Imported catalog row'
            );

            return [
                'action' => 'created',
                'product' => $product,
                'urls' => (string) ($fields[ProductImportField::IMAGE_URLS] ?? ''),
            ];
        }

        $meta = $this->mergeMetaLayer($product->meta ?? [], $extras, $catalogMeta, $variantOptions, $stockAlert, $productCustom);
        $brandName = trim((string) ($fields[ProductImportField::BRAND] ?? ''));
        $brandId = $brandName !== '' ? $taxonomyCache->brandId($brandName) : null;

        $product->update([
            'name' => $name,
            'slug' => $this->uniqueProductSlug($store->id, $name, $product->id),
            'description' => $description !== '' ? $description : $product->description,
            'base_price' => $basePrice,
            'sku' => $productSku,
            'product_type' => $productType,
            'status' => $status,
            'brand_id' => $brandId ?? $product->brand_id,
            'meta' => $meta,
        ]);

        $this->syncTaxonomy($product, $fields, $taxonomyCache);

        $variant = $product->variants()->whereDoesntHave('options')->orderBy('id')->first();
        if (! $variant) {
            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $variantSku,
                'price' => $basePrice,
                'stock' => 0,
                'stock_alert' => max(0, $stockAlert),
            ]);
            $variant->options()->sync([]);
        }

        $previousStock = (int) $variant->stock;
        $variant->update([
            'sku' => $variantSku,
            'price' => $basePrice,
            'stock' => $stock,
            'stock_alert' => max(0, $stockAlert),
        ]);

        $variant->refresh();
        $product->refresh();

        StockMovementRecorder::recordImport(
            $store,
            $product,
            $variant,
            $previousStock,
            $stock,
            $performedBy,
            $importId,
            'Imported catalog row'
        );

        $variant->refresh();
        $this->mergeVariantCustomFields($variant, $variantCustom);

        return [
            'action' => 'updated',
            'product' => $product,
            'urls' => (string) ($fields[ProductImportField::IMAGE_URLS] ?? ''),
        ];
    }

    /**
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function extractCustomFieldValues(array $row, array $customMappings): array
    {
        $product = [];
        $variant = [];
        foreach ($customMappings as $map) {
            $src = $map['source'];
            $key = $map['key'];
            $scope = $map['scope'];
            $val = trim((string) ($row[$src] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($scope === 'variant') {
                $variant[$key] = $val;
            } else {
                $product[$key] = $val;
            }
        }

        return [$product, $variant];
    }

    /**
     * @param  array<string, string>  $variantCustom
     */
    private function mergeVariantCustomFields(ProductVariant $variant, array $variantCustom): void
    {
        if ($variantCustom === []) {
            return;
        }
        $meta = $variant->meta ?? [];
        $meta['custom_fields'] = array_merge($meta['custom_fields'] ?? [], $variantCustom);
        $variant->update(['meta' => $meta]);
    }

    /**
     * @param  mixed  $raw
     * @return list<array{source: string, key: string, scope: string}>
     */
    public static function normalizeCustomMappings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $source = trim((string) ($entry['source'] ?? ''));
            $key = trim((string) ($entry['key'] ?? ''));
            $scope = strtolower(trim((string) ($entry['scope'] ?? 'product')));
            if ($scope !== 'variant') {
                $scope = 'product';
            }
            if ($source === '' || $key === '') {
                continue;
            }
            if (preg_match('/^[a-zA-Z0-9_.-]{1,128}$/', $key) !== 1) {
                continue;
            }
            $reserved = array_flip(array_map('strtolower', array_keys(ProductImportField::labels())));
            if (isset($reserved[strtolower($key)])) {
                continue;
            }
            $out[] = ['source' => $source, 'key' => $key, 'scope' => $scope];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $existingMeta
     * @param  array<string, string>  $extras
     * @param  array<string, mixed>  $catalogMeta
     * @param  array<string, string>  $variantOptions
     * @param  array<string, string>  $productCustomFields
     * @return array<string, mixed>
     */
    private function mergeMetaLayer(
        array $existingMeta,
        array $extras,
        array $catalogMeta,
        array $variantOptions,
        int $stockAlert,
        array $productCustomFields = [],
    ): array {
        $meta = $existingMeta;
        if ($extras !== []) {
            $meta['import_extra'] = array_merge($meta['import_extra'] ?? [], $extras);
        }
        if ($catalogMeta !== []) {
            $meta['catalog'] = array_merge($meta['catalog'] ?? [], $catalogMeta);
        }
        if ($variantOptions !== []) {
            $meta['import_variant_options'] = array_merge($meta['import_variant_options'] ?? [], $variantOptions);
        }
        if ($productCustomFields !== []) {
            $meta['custom_fields'] = array_merge($meta['custom_fields'] ?? [], $productCustomFields);
        }
        if ($stockAlert > 0) {
            $meta['stock_alert'] = $stockAlert;
        }

        return $meta;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function syncTaxonomy(Product $product, array $fields, ProductImportTaxonomyCache $taxonomyCache): void
    {
        $categoryIds = [];
        foreach ($this->splitDelimited($fields[ProductImportField::CATEGORY] ?? '') as $catName) {
            if ($catName === '') {
                continue;
            }
            $id = $taxonomyCache->categoryId($catName);
            if ($id > 0) {
                $categoryIds[] = $id;
            }
        }
        $product->categories()->sync(array_values(array_unique($categoryIds)));

        $tagIds = [];
        foreach ($this->splitDelimited($fields[ProductImportField::TAGS] ?? '') as $tagName) {
            if ($tagName === '') {
                continue;
            }
            $id = $taxonomyCache->tagId($tagName);
            if ($id > 0) {
                $tagIds[] = $id;
            }
        }
        $product->tags()->sync(array_values(array_unique($tagIds)));
    }

    private function uniqueProductSlug(int $storeId, string $name, ?int $ignoreProductId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'product';
        $slug = $base;
        $counter = 1;
        while (Product::query()->where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, true>  $assignedLower
     */
    private function ensureUniqueVariantSku(string $desiredSku, int $storeId, array &$assignedLower): string
    {
        $sku = $desiredSku !== '' ? $desiredSku : 'SKU-'.$storeId.'-'.Str::upper(Str::random(6));
        $base = $sku;
        $n = 0;
        while (true) {
            $lk = mb_strtolower($sku);
            if (isset($assignedLower[$lk]) || ProductVariant::query()->where('sku', $sku)->exists()) {
                $n++;
                $sku = Str::limit($base, 90, '').'-'.$storeId.'-'.$n;

                continue;
            }
            break;
        }
        $assignedLower[mb_strtolower($sku)] = true;

        return $sku;
    }

    private function normalizeProductType(string $type): string
    {
        $type = strtolower(trim($type));

        return $type !== '' ? $type : 'physical';
    }

    private function parseStatus(string $statusField, string $visibilityField): bool
    {
        $s = trim($statusField);
        $v = trim($visibilityField);
        $bool = SpreadsheetValueNormalizer::normalizeBoolean($s !== '' ? $s : $v);
        if ($bool !== null) {
            return $bool;
        }
        $raw = strtolower($s !== '' ? $s : $v);
        if ($raw === '' || $raw === 'published' || $raw === 'active' || $raw === 'visible') {
            return true;
        }
        if ($raw === 'draft' || $raw === 'hidden' || $raw === 'inactive') {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    private function cellsToKeyedRow(array $headers, array $cells): array
    {
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $cells[$i] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function extractMappedFields(array $row, array $mapping): array
    {
        $allowed = array_flip(array_keys(ProductImportField::labels()));
        $out = [];
        foreach ($mapping as $field => $sourceHeader) {
            if (! isset($allowed[$field])) {
                continue;
            }
            if (! is_string($sourceHeader) || $sourceHeader === '') {
                continue;
            }
            $out[$field] = $row[$sourceHeader] ?? '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @param  array<string, string>  $mapping
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array<string, string>
     */
    private function collectUnmappedExtras(array $row, array $headers, array $mapping, array $customMappings): array
    {
        $used = array_filter(array_values($mapping), static fn ($h) => is_string($h) && $h !== '');
        foreach ($customMappings as $cm) {
            $used[] = $cm['source'];
        }
        $used = array_values(array_unique($used));
        $extras = [];
        foreach ($headers as $h) {
            if ($h === '' || in_array($h, $used, true)) {
                continue;
            }
            $val = trim((string) ($row[$h] ?? ''));
            if ($val !== '') {
                $extras[$h] = $val;
            }
        }

        return $extras;
    }

    /**
     * @return list<string>
     */
    private function splitDelimited(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        foreach (['|', ';', "\n"] as $delim) {
            if (str_contains($value, $delim)) {
                return array_values(array_filter(array_map('trim', explode($delim, $value))));
            }
        }
        if (str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [$value];
    }
}
