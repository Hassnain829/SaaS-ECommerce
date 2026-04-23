<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue connection for catalog imports
    |--------------------------------------------------------------------------
    |
    | Set PRODUCT_IMPORT_QUEUE_CONNECTION only when imports must use a different
    | backend than the rest of the app. When unset or empty, imports follow
    | config('queue.default') (i.e. QUEUE_CONNECTION from .env).
    |
    | The resolved connection is copied to product_import.queue_connection on
    | each boot (see AppServiceProvider) so jobs and UI stay aligned.
    |
    | If you use database/redis, run workers, e.g.:
    |   php artisan queue:work --queue=default --tries=3
    |
    | If .env changes are ignored, clear cached config:
    |   php artisan optimize:clear
    |
    */
    'explicit_queue_connection' => env('PRODUCT_IMPORT_QUEUE_CONNECTION'),

    /*
    | Filled on boot from explicit_queue_connection + queue.default (see App\Support\Catalog\ProductImportQueue).
    */
    'queue_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Row streaming
    |--------------------------------------------------------------------------
    */
    'chunk_size' => max(50, min(2000, (int) env('PRODUCT_IMPORT_CHUNK_SIZE', 300))),

    'max_rows' => max(1000, min(500000, (int) env('PRODUCT_IMPORT_MAX_ROWS', 100000))),

    /*
    | When true, image HTTP downloads run in ProcessProductImageJob (one job per URL) after each
    | product row commits. When false, images are downloaded inline (tests / small files).
    */
    'async_image_processing' => filter_var(
        env('PRODUCT_IMPORT_ASYNC_IMAGES', true),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? true,

    /*
    |--------------------------------------------------------------------------
    | Stale import detection (result page + optional job guard)
    |--------------------------------------------------------------------------
    */
    'stale_queued_minutes' => (int) env('PRODUCT_IMPORT_STALE_QUEUED_MINUTES', 5),

    'stale_processing_minutes' => (int) env('PRODUCT_IMPORT_STALE_PROCESSING_MINUTES', 45),

    /*
    |--------------------------------------------------------------------------
    | Progress persistence (long imports)
    |--------------------------------------------------------------------------
    |
    | While processing, `result_summary.progress` is merged this often so the
    | result page can show live-ish counts without building a full dashboard.
    |
    */
    'progress_flush_every' => max(1, (int) env('PRODUCT_IMPORT_PROGRESS_FLUSH_EVERY', 25)),

    /*
    | When preview reported at least this many rows and the queue connection is
    | still "sync", a flash hint recommends running workers for very large files.
    |
    */
    'recommend_async_above_rows' => (int) env('PRODUCT_IMPORT_RECOMMEND_ASYNC_ABOVE_ROWS', 400),

    /*
    |--------------------------------------------------------------------------
    | product_import_rows.payload size (MySQL max_allowed_packet safety)
    |--------------------------------------------------------------------------
    |
    | Rows are slimmed before bulk insert. Increase only if your DB allows
    | larger packets; the primary fix is app-side truncation, not DB tuning.
    |
    */
    'row_payload_max_json_bytes' => max(8192, min(262144, (int) env('PRODUCT_IMPORT_ROW_PAYLOAD_MAX_JSON', 32768))),

    'row_payload_insert_batch_size' => max(20, min(200, (int) env('PRODUCT_IMPORT_ROW_BATCH', 80))),

    'row_payload_max_chars_unmapped' => max(50, min(4000, (int) env('PRODUCT_IMPORT_ROW_UNMAPPED_MAX', 400))),

    'row_payload_max_chars_mapped_default' => max(200, min(32000, (int) env('PRODUCT_IMPORT_ROW_MAPPED_DEFAULT_MAX', 2000))),

    'row_payload_max_chars_description' => max(500, min(64000, (int) env('PRODUCT_IMPORT_ROW_DESC_MAX', 4000))),

    'row_payload_max_chars_short_description' => max(200, min(8000, (int) env('PRODUCT_IMPORT_ROW_SHORT_DESC_MAX', 1500))),

    'row_payload_max_chars_image_urls_field' => max(500, min(64000, (int) env('PRODUCT_IMPORT_ROW_IMAGE_URLS_MAX', 8000))),

    'row_payload_max_image_urls_kept' => max(1, min(100, (int) env('PRODUCT_IMPORT_ROW_IMAGE_URLS_COUNT', 20))),

    'row_payload_max_chars_per_image_url' => max(64, min(2000, (int) env('PRODUCT_IMPORT_ROW_IMAGE_URL_MAX', 512))),

    'row_payload_max_chars_short_field' => max(64, min(1024, (int) env('PRODUCT_IMPORT_ROW_SHORT_FIELD_MAX', 512))),

    'row_payload_max_chars_medium_field' => max(200, min(16000, (int) env('PRODUCT_IMPORT_ROW_MEDIUM_FIELD_MAX', 2000))),

    'row_payload_max_chars_custom_source' => max(200, min(16000, (int) env('PRODUCT_IMPORT_ROW_CUSTOM_MAX', 2000))),

];
