<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime storage retention (CLEAN-3)
    |--------------------------------------------------------------------------
    |
    | Conservative defaults: retention disabled, dry-run default, force required.
    | See docs/operations/RUNTIME_STORAGE_RETENTION.md.
    |
    */

    'enabled' => env('PROJECT_RETENTION_ENABLED', false),

    'dry_run' => env('PROJECT_RETENTION_DRY_RUN', true),

    'require_force' => env('PROJECT_RETENTION_REQUIRE_FORCE', true),

    'preserve_protected' => env('PROJECT_RETENTION_PRESERVE_PROTECTED', true),

    'log_days' => (int) env('PROJECT_RETENTION_LOG_DAYS', 30),

    'cache_hours' => (int) env('PROJECT_RETENTION_CACHE_HOURS', 24),

    'source_archive_count' => (int) env('PROJECT_RETENTION_SOURCE_ARCHIVE_COUNT', 5),

    'source_archive_days' => (int) env('PROJECT_RETENTION_SOURCE_ARCHIVE_DAYS', 30),

    'validation_temp_days' => (int) env('PROJECT_RETENTION_VALIDATION_TEMP_DAYS', 14),

    'test_artifact_hours' => (int) env('PROJECT_RETENTION_TEST_ARTIFACT_HOURS', 24),

    'session_cleanup_enabled' => env('PROJECT_RETENTION_SESSION_CLEANUP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Testing-only sandbox enforcement (CLEAN-3A)
    |--------------------------------------------------------------------------
    |
    | When APP_ENV=testing, destructive --force runs require a marked sandbox
    | root outside the real repository. Not used in production.
    |
    */
    'test_sandbox_required' => env('PROJECT_RETENTION_TEST_SANDBOX_REQUIRED', true),

    'categories' => [
        'cache',
        'logs',
        'validation-temp',
        'source-archives',
        'test-artifacts',
    ],

    'schedule' => [
        'enabled' => env('PROJECT_RETENTION_SCHEDULE_ENABLED', false),
        'frequency' => env('PROJECT_RETENTION_SCHEDULE_FREQUENCY', 'daily'),
        'force' => env('PROJECT_RETENTION_SCHEDULE_FORCE', false),
        'categories' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('PROJECT_RETENTION_SCHEDULE_CATEGORIES', 'cache,validation-temp')),
        ))),
    ],

];
