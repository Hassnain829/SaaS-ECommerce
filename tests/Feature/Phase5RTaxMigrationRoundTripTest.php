<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase5RTaxMigrationRoundTripTest extends TestCase
{
    public function test_slice_1a_migrations_round_trip_on_isolated_file_sqlite(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ecommerce-office-slice1a-roundtrip-'.uniqid('', true).'.sqlite';
        touch($path);

        try {
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $path);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            Artisan::call('migrate:fresh', ['--force' => true]);

            $this->assertTrue(Schema::hasTable('tax_settings'));
            $this->assertTrue(Schema::hasTable('tax_rates'));
            $this->assertTrue(Schema::hasTable('checkout_tax_lines'));
            $this->assertTrue(Schema::hasTable('order_tax_lines'));
            $this->assertTrue(Schema::hasColumn('products', 'is_taxable'));

            Artisan::call('migrate:rollback', ['--step' => 5, '--force' => true]);

            $this->assertFalse(Schema::hasTable('order_tax_lines'));
            $this->assertFalse(Schema::hasTable('checkout_tax_lines'));
            $this->assertFalse(Schema::hasColumn('products', 'is_taxable'));
            $this->assertFalse(Schema::hasTable('tax_rates'));
            $this->assertFalse(Schema::hasTable('tax_settings'));

            Artisan::call('migrate', ['--force' => true]);

            $this->assertTrue(Schema::hasTable('tax_settings'));
            $this->assertTrue(Schema::hasTable('tax_rates'));
            $this->assertTrue(Schema::hasColumn('products', 'is_taxable'));
        } finally {
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', ':memory:');
            DB::purge('sqlite');
            DB::reconnect('sqlite');
            @unlink($path);
        }
    }
}
