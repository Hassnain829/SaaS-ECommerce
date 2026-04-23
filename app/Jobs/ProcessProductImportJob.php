<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Services\Catalog\ProductImportProcessor;
use App\Support\Catalog\ProductImportMerchantMessages;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProductImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Long-running catalog writes; keep below worker / platform hard limits.
     * For future fan-out, consider Bus::batch() with per-chunk jobs.
     */
    public int $timeout = 1200;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 120, 300];

    public function __construct(
        public int $productImportId,
    ) {
        $this->onConnection(ProductImportQueue::connection());
    }

    public function handle(ProductImportProcessor $processor): void
    {
        $import = ProductImport::query()->find($this->productImportId);
        if (! $import) {
            Log::channel('import')->warning('product_import_job_missing_import', [
                'product_import_id' => $this->productImportId,
            ]);

            return;
        }

        Log::channel('import')->info('product_import_job_started', [
            'import_id' => $import->id,
            'store_id' => $import->store_id,
            'status' => $import->status,
            'queue_connection' => ProductImportQueue::connection(),
        ]);

        $processor->run($import);

        $import->refresh();
        Log::channel('import')->info('product_import_job_finished', array_merge([
            'import_id' => $import->id,
            'store_id' => $import->store_id,
            'status' => $import->status,
        ], ProductImportQueue::diagnostics()));
    }

    public function failed(?Throwable $exception): void
    {
        $import = ProductImport::query()->find($this->productImportId);
        if (! $import) {
            return;
        }

        if (in_array($import->status, [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true)) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Import job failed.';
        Log::channel('import')->error('product_import_job_failed', [
            'import_id' => $import->id,
            'message' => $message,
        ]);

        $import->update([
            'status' => ProductImport::STATUS_FAILED,
            'failure_message' => ProductImportMerchantMessages::truncateForStorage(
                ProductImportMerchantMessages::humanizeRowError($message),
                2500
            ),
            'completed_at' => now(),
            'result_summary' => array_merge($import->result_summary ?? [], [
                'job_failed' => true,
            ]),
        ]);
    }
}
