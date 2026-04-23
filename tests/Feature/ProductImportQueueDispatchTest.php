<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImportJob;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportQueueDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_import_job_uses_resolved_sync_connection_when_no_override(): void
    {
        config(['queue.default' => 'sync']);
        config(['product_import.explicit_queue_connection' => null]);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $job = new ProcessProductImportJob(99);

        $this->assertSame('sync', $job->connection);
    }

    public function test_process_import_job_uses_explicit_connection_when_set(): void
    {
        config(['queue.default' => 'sync']);
        config(['product_import.explicit_queue_connection' => 'database']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $job = new ProcessProductImportJob(99);

        $this->assertSame('database', $job->connection);
    }
}
