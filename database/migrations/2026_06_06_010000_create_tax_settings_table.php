<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('prices_include_tax')->default(false);
            $table->boolean('default_product_taxable')->default(true);
            $table->boolean('shipping_taxable')->default(false);
            $table->string('calculation_address', 32)->default('shipping');
            $table->unsignedInteger('settings_version')->default(1);
            $table->timestamps();
        });

        $this->seedExistingStoreTaxSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }

    private function seedExistingStoreTaxSettings(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        $now = now();

        DB::table('stores')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($stores) use ($now): void {
                $rows = [];

                foreach ($stores as $store) {
                    $rows[] = [
                        'store_id' => $store->id,
                        'enabled' => false,
                        'prices_include_tax' => false,
                        'default_product_taxable' => true,
                        'shipping_taxable' => false,
                        'calculation_address' => 'shipping',
                        'settings_version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('tax_settings')->insertOrIgnore($rows);
                }
            });
    }
};
