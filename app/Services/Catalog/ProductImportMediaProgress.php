<?php

namespace App\Services\Catalog;

use App\Models\ProductImport;
use Illuminate\Support\Facades\Cache;

/**
 * Thread-safe updates to product_imports.result_summary image counters (async jobs + import worker).
 */
final class ProductImportMediaProgress
{
    private static function lockKey(int $importId): string
    {
        return 'product_import_media_progress:'.$importId;
    }

    /**
     * @param  int|null  $importId  When null, no-op (images not tied to an import record).
     */
    public static function adjust(?int $importId, int $totalDelta, int $processedDelta, int $failedDelta = 0): void
    {
        if (! $importId || ($totalDelta === 0 && $processedDelta === 0 && $failedDelta === 0)) {
            return;
        }

        $lock = Cache::lock(self::lockKey($importId), 30);
        $lock->block(25, function () use ($importId, $totalDelta, $processedDelta, $failedDelta): void {
            $import = ProductImport::query()->find($importId);
            if (! $import) {
                return;
            }
            $rs = is_array($import->result_summary) ? $import->result_summary : [];
            $rs['total_images'] = max(0, (int) ($rs['total_images'] ?? 0) + $totalDelta);
            $rs['processed_images'] = max(0, (int) ($rs['processed_images'] ?? 0) + $processedDelta);
            $rs['failed_images'] = max(0, (int) ($rs['failed_images'] ?? 0) + $failedDelta);
            $import->update(['result_summary' => $rs]);
        });
    }

    /**
     * @return array{products: array{total:int, processed:int}, images: array{total:int, processed:int, failed:int}}
     */
    public static function snapshot(ProductImport $import): array
    {
        $rs = is_array($import->result_summary) ? $import->result_summary : [];
        $totalRows = (int) ($import->total_rows ?? 0);
        if ($totalRows < 1) {
            $totalRows = (int) ($rs['total_rows'] ?? $rs['total_rows_estimated'] ?? 0);
        }

        $processedProducts = (int) ($rs['processed_products'] ?? 0);
        if ($processedProducts < 1 && isset($rs['progress']['processed_rows'])) {
            $processedProducts = (int) $rs['progress']['processed_rows'];
        }
        if ($processedProducts < 1) {
            $processedProducts = (int) ($rs['created'] ?? 0) + (int) ($rs['updated'] ?? 0) + (int) ($rs['skipped'] ?? 0) + (int) ($rs['failed'] ?? 0);
        }

        return [
            'products' => [
                'total' => max($totalRows, (int) ($rs['total_products'] ?? 0)),
                'processed' => $processedProducts,
            ],
            'images' => [
                'total' => (int) ($rs['total_images'] ?? 0),
                'processed' => (int) ($rs['processed_images'] ?? 0),
                'failed' => (int) ($rs['failed_images'] ?? 0),
            ],
        ];
    }
}
