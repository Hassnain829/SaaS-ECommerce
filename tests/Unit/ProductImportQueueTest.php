<?php

namespace Tests\Unit;

use App\Support\Catalog\ProductImportQueue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportQueueTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_queue_default_when_explicit_is_empty(): void
    {
        config(['product_import.explicit_queue_connection' => null]);
        config(['queue.default' => 'sync']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $this->assertSame('sync', ProductImportQueue::connection());
        $this->assertTrue(ProductImportQueue::runsInline());
    }

    #[Test]
    public function it_trims_explicit_connection(): void
    {
        config(['product_import.explicit_queue_connection' => '  redis  ']);
        config(['queue.default' => 'sync']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $this->assertSame('redis', ProductImportQueue::connection());
        $this->assertFalse(ProductImportQueue::runsInline());
    }

    #[Test]
    public function it_uses_explicit_override_over_queue_default(): void
    {
        config(['product_import.explicit_queue_connection' => 'database']);
        config(['queue.default' => 'sync']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $this->assertSame('database', ProductImportQueue::connection());
        $this->assertFalse(ProductImportQueue::runsInline());
    }

    #[Test]
    public function it_treats_blank_explicit_as_missing(): void
    {
        config(['product_import.explicit_queue_connection' => '   ']);
        config(['queue.default' => 'sync']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $this->assertSame('sync', ProductImportQueue::connection());
    }

    #[Test]
    public function it_falls_back_to_database_when_queue_default_is_empty_string(): void
    {
        config(['product_import.explicit_queue_connection' => null]);
        config(['queue.default' => '  ']);
        config(['product_import.queue_connection' => ProductImportQueue::connection()]);

        $this->assertSame('database', ProductImportQueue::connection());
    }
}
