<?php

namespace App\Services\Catalog;

use App\Models\ProductImport;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Support\Facades\Log;

/**
 * Marks abandoned imports so the result UI never spins forever without explanation.
 */
final class ProductImportStaleHandler
{
    public static function resolveIfStale(ProductImport $import): ProductImport
    {
        $import->refresh();

        if ($import->status === ProductImport::STATUS_QUEUED && ! ProductImportQueue::runsInline()) {
            $queuedAt = $import->queued_at ?? $import->updated_at ?? $import->created_at;
            $minutes = max(1, (int) config('product_import.stale_queued_minutes', 5));
            if ($queuedAt && $queuedAt->lt(now()->subMinutes($minutes))) {
                $queueConn = ProductImportQueue::connection();
                $msg = 'This import did not begin within the expected time. On this site, catalog imports are handled in the background; '
                    .'that service may not be running, or it may be busy. Ask your administrator to verify background workers are running, then try importing again.';
                Log::channel('import')->warning('product_import_stale_queued', [
                    'import_id' => $import->id,
                    'store_id' => $import->store_id,
                    'queued_at' => $queuedAt->toIso8601String(),
                    'stale_after_minutes' => $minutes,
                    'queue_connection' => $queueConn,
                ]);
                $import->update([
                    'status' => ProductImport::STATUS_FAILED,
                    'failure_message' => $msg,
                    'completed_at' => now(),
                    'result_summary' => array_merge($import->result_summary ?? [], [
                        'stale_reason' => 'queued_timeout',
                        'hint' => 'Your file is still saved in import history; you can upload a new copy if you need to change anything.',
                    ]),
                ]);
            }
        }

        if ($import->status === ProductImport::STATUS_PROCESSING) {
            $started = $import->started_at;
            $minutes = max(1, (int) config('product_import.stale_processing_minutes', 45));
            if ($started && $started->lt(now()->subMinutes($minutes))) {
                Log::channel('import')->warning('product_import_stale_processing', [
                    'import_id' => $import->id,
                    'store_id' => $import->store_id,
                    'started_at' => $started->toIso8601String(),
                    'stale_after_minutes' => $minutes,
                ]);
                $import->update([
                    'status' => ProductImport::STATUS_FAILED,
                    'failure_message' => 'This import stopped before it could finish—often because the file is very large or the server timed out. Try splitting the file into smaller imports, or ask your host to allow longer-running catalog jobs.',
                    'completed_at' => now(),
                    'result_summary' => array_merge($import->result_summary ?? [], [
                        'stale_reason' => 'processing_timeout',
                        'hint' => 'You can try “Continue from where it stopped” if that option appears, or start a new import with fewer rows.',
                    ]),
                ]);
            }
        }

        return $import->fresh() ?? $import;
    }
}
