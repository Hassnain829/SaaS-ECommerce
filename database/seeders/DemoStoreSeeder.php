<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Requires {@see UserSeeder} (called from {@see DatabaseSeeder} before this).
     * Creates demo stores and catalog taxonomy; products are created through the app when you exercise flows.
     */
    public function run(): void
    {
        $merchant = User::query()
            ->where('email', 'user@erdcore.test')
            ->firstOrFail();
        $manager = User::query()
            ->where('email', 'manager@erdcore.test')
            ->firstOrFail();
        $staff = User::query()
            ->where('email', 'staff@erdcore.test')
            ->firstOrFail();

        $fashionStore = Store::query()->updateOrCreate(
            ['slug' => 'demo-fashion'],
            [
                'user_id' => $merchant->id,
                'name' => 'Demo Fashion',
                'logo' => null,
                'address' => 'Karachi, Pakistan',
                'currency' => 'PKR',
                'timezone' => 'Asia/Karachi',
                'category' => 'physical',
                'settings' => [
                    'primary_market' => 'South Asia',
                    'business_models' => ['Retail'],
                ],
                'onboarding_completed' => true,
            ]
        );

        $digitalStore = Store::query()->updateOrCreate(
            ['slug' => 'demo-digital'],
            [
                'user_id' => $merchant->id,
                'name' => 'Demo Digital',
                'logo' => null,
                'address' => 'Lahore, Pakistan',
                'currency' => 'USD',
                'timezone' => 'Asia/Karachi',
                'category' => 'digital',
                'settings' => [
                    'primary_market' => 'Global Market',
                    'business_models' => ['Digital Products'],
                ],
                'onboarding_completed' => true,
            ]
        );

        $fashionStore->members()->syncWithoutDetaching([
            $merchant->id => ['role' => Store::ROLE_OWNER],
            $manager->id => ['role' => Store::ROLE_MANAGER],
            $staff->id => ['role' => Store::ROLE_STAFF],
        ]);

        $digitalStore->members()->syncWithoutDetaching([
            $merchant->id => ['role' => Store::ROLE_OWNER],
        ]);

        $brandRows = [
            [
                'name' => 'Nike',
                'slug' => 'nike',
                'short_description' => 'Performance athletic brand',
                'sort_order' => 1,
                'featured' => true,
            ],
            [
                'name' => 'Adidas',
                'slug' => 'adidas',
                'short_description' => 'Sportswear and lifestyle',
                'sort_order' => 2,
                'featured' => false,
            ],
            [
                'name' => 'Generic',
                'slug' => 'generic',
                'short_description' => 'House label products',
                'sort_order' => 3,
                'featured' => false,
            ],
        ];

        foreach ($brandRows as $row) {
            Brand::query()->updateOrCreate(
                [
                    'store_id' => $fashionStore->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'short_description' => $row['short_description'],
                    'description' => null,
                    'logo' => null,
                    'status' => 'active',
                    'sort_order' => $row['sort_order'],
                    'featured' => $row['featured'],
                    'seo_title' => null,
                    'seo_description' => null,
                    'meta' => null,
                    'created_by' => $merchant->id,
                    'updated_by' => $merchant->id,
                ]
            );
        }

        $nikeBrand = Brand::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'nike')
            ->first();
        $adidasBrand = Brand::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'adidas')
            ->first();

        if ($nikeBrand) {
            Product::query()
                ->where('store_id', $fashionStore->id)
                ->whereNull('brand_id')
                ->orderBy('id')
                ->limit(2)
                ->update(['brand_id' => $nikeBrand->id]);
        }

        if ($adidasBrand) {
            Product::query()
                ->where('store_id', $fashionStore->id)
                ->whereNull('brand_id')
                ->orderBy('id')
                ->limit(1)
                ->update(['brand_id' => $adidasBrand->id]);
        }

        $demoTagRows = [
            ['name' => 'Featured', 'slug' => 'featured', 'sort_order' => 1],
            ['name' => 'New Arrival', 'slug' => 'new-arrival', 'sort_order' => 2],
            ['name' => 'Sale', 'slug' => 'sale', 'sort_order' => 3],
        ];

        foreach ($demoTagRows as $row) {
            Tag::query()->updateOrCreate(
                [
                    'store_id' => $fashionStore->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'color' => null,
                    'status' => 'active',
                    'sort_order' => $row['sort_order'],
                    'created_by' => $merchant->id,
                    'updated_by' => $merchant->id,
                ]
            );
        }

        $featuredTag = Tag::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'featured')
            ->first();
        $newArrivalTag = Tag::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'new-arrival')
            ->first();
        $saleTag = Tag::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'sale')
            ->first();

        $attachProducts = Product::query()
            ->where('store_id', $fashionStore->id)
            ->orderBy('id')
            ->limit(4)
            ->get();

        if ($featuredTag && $attachProducts->isNotEmpty()) {
            $attachProducts->first()->tags()->syncWithoutDetaching([$featuredTag->id]);
        }

        if ($newArrivalTag && $attachProducts->count() > 1) {
            $second = $attachProducts->slice(1, 1)->first();
            $second?->tags()->syncWithoutDetaching([$newArrivalTag->id]);
        }

        if ($saleTag && $attachProducts->count() > 2) {
            foreach ($attachProducts->slice(2, 2) as $product) {
                $product->tags()->syncWithoutDetaching([$saleTag->id]);
            }
        }

        $demoCategoryRows = [
            ['name' => 'Clothing', 'slug' => 'clothing', 'sort_order' => 1],
            ['name' => 'Shoes', 'slug' => 'shoes', 'sort_order' => 2],
            ['name' => 'Accessories', 'slug' => 'accessories', 'sort_order' => 3],
        ];

        foreach ($demoCategoryRows as $row) {
            Category::query()->updateOrCreate(
                [
                    'store_id' => $fashionStore->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'parent_id' => null,
                    'sort_order' => $row['sort_order'],
                    'status' => 'active',
                ]
            );
        }

        $clothingCategory = Category::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'clothing')
            ->first();
        $shoesCategory = Category::query()
            ->where('store_id', $fashionStore->id)
            ->where('slug', 'shoes')
            ->first();

        $categoryAttachProducts = Product::query()
            ->where('store_id', $fashionStore->id)
            ->orderBy('id')
            ->limit(4)
            ->get();

        if ($clothingCategory && $categoryAttachProducts->isNotEmpty()) {
            $categoryAttachProducts->first()->categories()->syncWithoutDetaching([$clothingCategory->id]);
        }

        if ($shoesCategory && $categoryAttachProducts->count() > 1) {
            $categoryAttachProducts->slice(1, 1)->first()?->categories()->syncWithoutDetaching([$shoesCategory->id]);
        }

        if ($clothingCategory && $shoesCategory && $categoryAttachProducts->count() > 2) {
            foreach ($categoryAttachProducts->slice(2, 2) as $product) {
                $product->categories()->syncWithoutDetaching([$clothingCategory->id, $shoesCategory->id]);
            }
        }
    }
}
