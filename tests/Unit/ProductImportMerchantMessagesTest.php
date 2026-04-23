<?php

namespace Tests\Unit;

use App\Support\Catalog\ProductImportMerchantMessages;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportMerchantMessagesTest extends TestCase
{
    #[Test]
    public function humanize_exception_does_not_preserve_massive_sql_payload(): void
    {
        $sql = 'INSERT INTO product_import_rows (`payload`) VALUES (\''.str_repeat('x', 15000).'\')';
        $e = new Exception('SQLSTATE[HY000]: General error: '.$sql);

        $out = ProductImportMerchantMessages::humanizeException($e);

        $this->assertLessThan(900, strlen($out));
        $this->assertStringNotContainsString(str_repeat('x', 500), $out);
        $this->assertStringContainsString('data conflict', $out);
    }

    #[Test]
    public function humanize_exception_maps_packet_size_errors(): void
    {
        $e = new Exception('SQLSTATE[08S01]: Communication link failure: 1153 Got a packet bigger than max_allowed_packet bytes');

        $out = ProductImportMerchantMessages::humanizeException($e);

        $this->assertStringContainsString('max_allowed_packet', $out);
        $this->assertLessThan(600, strlen($out));
    }
}
