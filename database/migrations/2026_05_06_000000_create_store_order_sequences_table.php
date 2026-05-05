<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_order_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1001);
            $table->timestamps();
        });

        $this->seedExistingStoreSequences();

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_number')) {
            $dropReferenceUnique = Schema::hasIndex('orders', 'orders_reference_unique');
            $dropOrderNumberUnique = Schema::hasIndex('orders', 'orders_order_number_unique');
            $addStoreScopedUnique = ! Schema::hasIndex('orders', 'orders_store_id_order_number_unique');

            Schema::table('orders', function (Blueprint $table) use ($dropReferenceUnique, $dropOrderNumberUnique, $addStoreScopedUnique): void {
                if ($dropReferenceUnique) {
                    $table->dropUnique('orders_reference_unique');
                }

                if ($dropOrderNumberUnique) {
                    $table->dropUnique('orders_order_number_unique');
                }

                if ($addStoreScopedUnique) {
                    $table->unique(['store_id', 'order_number'], 'orders_store_id_order_number_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasIndex('orders', 'orders_store_id_order_number_unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique('orders_store_id_order_number_unique');
            });
        }

        Schema::dropIfExists('store_order_sequences');
    }

    private function seedExistingStoreSequences(): void
    {
        DB::table('stores')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($stores): void {
                foreach ($stores as $store) {
                    $maxOrderNumber = 1000;

                    if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_number')) {
                        DB::table('orders')
                            ->where('store_id', $store->id)
                            ->whereNotNull('order_number')
                            ->orderBy('id')
                            ->pluck('order_number')
                            ->each(function ($orderNumber) use (&$maxOrderNumber): void {
                                if (preg_match('/#?(\d+)$/', (string) $orderNumber, $matches)) {
                                    $maxOrderNumber = max($maxOrderNumber, (int) $matches[1]);
                                }
                            });
                    }

                    DB::table('store_order_sequences')->insert([
                        'store_id' => $store->id,
                        'next_number' => max(1001, $maxOrderNumber + 1),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
