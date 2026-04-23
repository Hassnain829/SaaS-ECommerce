<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Services\Catalog\ProductImportProcessor;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-runs only failed catalog rows for an import (uses stored row payloads).
 */
class RetryFailedProductImportRowsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public int $productImportId,
    ) {
        $this->onConnection(ProductImportQueue::connection());
    }

    public function handle(ProductImportProcessor $processor): void
    {
        $import = ProductImport::query()->find($this->productImportId);
        if (! $import) {
            return;
        }

        $processor->retryFailedRows($import);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('import')->error('retry_failed_import_rows_job_failed', [
            'import_id' => $this->productImportId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
