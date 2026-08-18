<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table): void {
            $table->string('source_site', 255)->nullable()->after('file_extension');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->string('source_site', 255)->nullable()->after('source_system');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('source_system', 40)->nullable()->after('sku');
            $table->string('source_site', 255)->nullable()->after('source_system');
        });

        foreach (DB::table('product_imports')->whereNotNull('import_state')->orderBy('id')->get() as $import) {
            $state = json_decode((string) $import->import_state, true);
            $sourceSite = is_array($state) ? $this->normalizeUrl((string) ($state['source_site'] ?? '')) : null;
            if ($sourceSite !== null) {
                DB::table('product_imports')->where('id', $import->id)->update(['source_site' => $sourceSite]);
            }
        }

        foreach (DB::table('products')->where('source_system', 'woocommerce')->orderBy('id')->get() as $product) {
            $meta = json_decode((string) ($product->meta ?? ''), true);
            $sourceSite = is_array($meta)
                ? $this->normalizeUrl((string) data_get($meta, 'source_identity.site', ''))
                : null;
            if ($sourceSite !== null) {
                DB::table('products')->where('id', $product->id)->update(['source_site' => $sourceSite]);
            }
        }

        foreach (DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNotNull('product_variants.source_variation_id')
            ->where('products.source_system', 'woocommerce')
            ->select([
                'product_variants.id',
                'products.source_system',
                'products.source_site',
            ])
            ->orderBy('product_variants.id')
            ->get() as $variant) {
            DB::table('product_variants')->where('id', $variant->id)->update([
                'source_system' => $variant->source_system,
                'source_site' => $variant->source_site,
            ]);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_store_source_identity_unique');
            $table->unique(
                ['store_id', 'source_system', 'source_site', 'source_product_id'],
                'products_store_source_site_identity_unique'
            );
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_store_source_variation_unique');
            $table->unique(
                ['store_id', 'source_system', 'source_site', 'source_variation_id'],
                'product_variants_store_source_site_variation_unique'
            );
        });
    }

    public function down(): void
    {
        $productDuplicates = DB::table('products')
            ->whereNotNull('source_product_id')
            ->select(['store_id', 'source_system', 'source_product_id'])
            ->groupBy(['store_id', 'source_system', 'source_product_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $variantDuplicates = DB::table('product_variants')
            ->whereNotNull('source_variation_id')
            ->select(['store_id', 'source_variation_id'])
            ->groupBy(['store_id', 'source_variation_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($productDuplicates || $variantDuplicates) {
            throw new RuntimeException('Cannot roll back WooCommerce source-site identity because multiple source sites now use the same WooCommerce IDs.');
        }

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_store_source_site_variation_unique');
            $table->unique(['store_id', 'source_variation_id'], 'product_variants_store_source_variation_unique');
            $table->dropColumn(['source_system', 'source_site']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_store_source_site_identity_unique');
            $table->unique(['store_id', 'source_system', 'source_product_id'], 'products_store_source_identity_unique');
            $table->dropColumn('source_site');
        });
        Schema::table('product_imports', function (Blueprint $table): void {
            $table->dropColumn('source_site');
        });
    }

    private function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }
};
