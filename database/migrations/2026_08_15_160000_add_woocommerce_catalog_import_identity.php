<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'source_system')) {
                $table->string('source_system', 40)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('products', 'source_product_id')) {
                $table->string('source_product_id', 64)->nullable()->after('source_system');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasIndex('products', 'products_store_source_identity_unique')) {
                $table->unique(
                    ['store_id', 'source_system', 'source_product_id'],
                    'products_store_source_identity_unique'
                );
            }
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_variants', 'source_variation_id')) {
                $table->string('source_variation_id', 64)->nullable()->after('sku');
            }
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (! Schema::hasIndex('product_variants', 'product_variants_store_source_variation_unique')) {
                $table->unique(
                    ['store_id', 'source_variation_id'],
                    'product_variants_store_source_variation_unique'
                );
            }
        });

        if (! Schema::hasTable('product_url_redirects')) {
            Schema::create('product_url_redirects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_import_id')->nullable()->constrained('product_imports')->nullOnDelete();
                $table->string('source_slug', 191);
                $table->string('source_path', 255);
                $table->string('destination_slug', 191);
                $table->timestamps();

                $table->unique(['store_id', 'source_path'], 'product_url_redirects_store_source_path_unique');
                $table->index(['store_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_url_redirects');

        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasIndex('product_variants', 'product_variants_store_source_variation_unique')) {
                $table->dropUnique('product_variants_store_source_variation_unique');
            }
            if (Schema::hasColumn('product_variants', 'source_variation_id')) {
                $table->dropColumn('source_variation_id');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasIndex('products', 'products_store_source_identity_unique')) {
                $table->dropUnique('products_store_source_identity_unique');
            }
            if (Schema::hasColumn('products', 'source_product_id')) {
                $table->dropColumn('source_product_id');
            }
            if (Schema::hasColumn('products', 'source_system')) {
                $table->dropColumn('source_system');
            }
        });
    }
};
