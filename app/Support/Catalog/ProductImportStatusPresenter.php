<?php

namespace App\Support\Catalog;

use App\Models\ProductImport;

/**
 * Merchant-facing labels for import workflow states (not internal job wording).
 */
final class ProductImportStatusPresenter
{
    public static function label(string $status): string
    {
        return match ($status) {
            ProductImport::STATUS_UPLOADED => 'File received',
            ProductImport::STATUS_PARSED => 'Columns detected',
            ProductImport::STATUS_PREVIEWED => 'Ready to run',
            ProductImport::STATUS_QUEUED => 'Waiting to start',
            ProductImport::STATUS_PROCESSING => 'Import in progress',
            ProductImport::STATUS_COMPLETED => 'Finished',
            ProductImport::STATUS_FAILED => 'Could not finish',
            default => 'In progress',
        };
    }

    /**
     * Short badge color token for Blade (tailwind-friendly).
     */
    public static function badgeClass(string $status): string
    {
        return match ($status) {
            ProductImport::STATUS_COMPLETED => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            ProductImport::STATUS_FAILED => 'bg-red-50 text-red-800 ring-red-200',
            ProductImport::STATUS_PROCESSING, ProductImport::STATUS_QUEUED => 'bg-sky-50 text-sky-800 ring-sky-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
