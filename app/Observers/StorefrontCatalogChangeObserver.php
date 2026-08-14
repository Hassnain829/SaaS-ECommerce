<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\StorefrontCatalogEventRecorder;

class StorefrontCatalogChangeObserver
{
    public function __construct(
        private readonly StorefrontCatalogEventRecorder $recorder,
    ) {}

    public function created(Product|ProductVariant|Category|ProductImage $model): void
    {
        if ($model instanceof Product) {
            $this->recorder->recordProductCreated($model);

            return;
        }

        if ($model instanceof ProductVariant) {
            $this->recorder->recordVariantSaved($model, created: true);

            return;
        }

        if ($model instanceof Category) {
            $this->recorder->recordCategorySaved($model, created: true);

            return;
        }

        $product = $model->product;
        if ($product) {
            $product->touch();
        }
    }

    public function updated(Product|ProductVariant|Category|ProductImage $model): void
    {
        if ($model instanceof Product) {
            $this->recorder->recordProductUpdated($model);

            return;
        }

        if ($model instanceof ProductVariant) {
            $this->recorder->recordVariantSaved($model, created: false);

            return;
        }

        if ($model instanceof Category) {
            $this->recorder->recordCategorySaved($model, created: false);

            return;
        }

        $product = $model->product;
        if ($product) {
            $product->touch();
        }
    }

    public function deleted(Product|ProductVariant|Category|ProductImage $model): void
    {
        if ($model instanceof Product) {
            $this->recorder->recordProductDeleted($model);

            return;
        }

        if ($model instanceof ProductVariant) {
            $this->recorder->recordVariantDeleted($model);

            return;
        }

        if ($model instanceof Category) {
            $this->recorder->recordCategoryDeleted($model);

            return;
        }

        $product = $model->product;
        if ($product) {
            $product->touch();
        }
    }

    public function restored(Product $product): void
    {
        $this->recorder->recordProductRestored($product);
    }
}
