<?php

namespace App\Support\ProjectHygiene;

/**
 * Documented runtime storage categories for retention and hygiene reporting.
 *
 * @see docs/operations/RUNTIME_STORAGE_RETENTION.md
 */
final class ProjectStorageClassification
{
    /** @var list<string> */
    public const RETENTION_CATEGORIES = [
        'cache',
        'logs',
        'validation-temp',
        'source-archives',
        'test-artifacts',
    ];

    /** @var list<string> */
    public const FORBIDDEN_DELETION_PREFIXES = [
        'app/',
        'bootstrap/app.php',
        'config/',
        'database/',
        'public/',
        'resources/',
        'routes/',
        'tests/',
        'vendor/',
        'node_modules/',
        'dev-test-storefront/',
        '.git/',
        'storage/app/public/',
        'storage/app/private/',
    ];

    /** @var list<string> */
    public const MERCHANT_ARTIFACT_PREFIXES = [
        'storage/app/public/',
        'storage/app/private/',
    ];

    /**
     * @return list<array{category: string, path: string, purpose: string, retention: string, protected: bool}>
     */
    public static function catalog(): array
    {
        return [
            [
                'category' => 'cache',
                'path' => 'bootstrap/cache/*.php',
                'purpose' => 'Compiled bootstrap cache',
                'retention' => 'config project_retention.cache_hours',
                'protected' => false,
            ],
            [
                'category' => 'cache',
                'path' => 'storage/framework/cache/data/*',
                'purpose' => 'Framework cache data',
                'retention' => 'config project_retention.cache_hours',
                'protected' => false,
            ],
            [
                'category' => 'cache',
                'path' => 'storage/framework/views/*.php',
                'purpose' => 'Compiled Blade views',
                'retention' => 'config project_retention.cache_hours',
                'protected' => false,
            ],
            [
                'category' => 'cache',
                'path' => 'storage/framework/sessions/*',
                'purpose' => 'File session payloads (opt-in only)',
                'retention' => 'disabled unless session_cleanup_enabled',
                'protected' => false,
            ],
            [
                'category' => 'logs',
                'path' => 'storage/logs/*.log',
                'purpose' => 'Application and diagnostic logs',
                'retention' => 'config project_retention.log_days',
                'protected' => false,
            ],
            [
                'category' => 'validation-temp',
                'path' => 'storage/app/fedex-validation/*/*/FedEx_Integrator_Validation_BaasPlatformFedExSandbox',
                'purpose' => 'FedEx export staging workspace',
                'retention' => 'config project_retention.validation_temp_days',
                'protected' => false,
            ],
            [
                'category' => 'validation-temp',
                'path' => 'storage/app/fedex-validation/*/*/fedex-validation-diagnostic-*.zip',
                'purpose' => 'Non-final FedEx diagnostic ZIP exports',
                'retention' => 'config project_retention.validation_temp_days',
                'protected' => false,
            ],
            [
                'category' => 'protected',
                'path' => 'storage/app/fedex-validation/*/labels/',
                'purpose' => 'Production and validation shipping labels',
                'retention' => 'never auto-deleted',
                'protected' => true,
            ],
            [
                'category' => 'protected',
                'path' => 'storage/app/fedex-validation/*/uploads/',
                'purpose' => 'Printed scans and merchant-uploaded evidence',
                'retention' => 'never auto-deleted',
                'protected' => true,
            ],
            [
                'category' => 'protected',
                'path' => 'storage/app/fedex-validation/*/*/fedex-validation-final-*.zip',
                'purpose' => 'Canonical final FedEx submission packages',
                'retention' => 'never auto-deleted',
                'protected' => true,
            ],
            [
                'category' => 'validation-temp',
                'path' => 'storage/app/usps-validation/**/staging',
                'purpose' => 'USPS validation staging directories',
                'retention' => 'config project_retention.validation_temp_days',
                'protected' => false,
            ],
            [
                'category' => 'source-archives',
                'path' => 'storage/app/source-archives/E_COMMERCE_OFFICE-source-*.zip',
                'purpose' => 'Generated Git source archives',
                'retention' => 'latest N + max age',
                'protected' => false,
            ],
            [
                'category' => 'test-artifacts',
                'path' => '.phpunit.cache/*',
                'purpose' => 'PHPUnit cache',
                'retention' => 'config project_retention.test_artifact_hours',
                'protected' => false,
            ],
            [
                'category' => 'merchant',
                'path' => 'storage/app/public/*',
                'purpose' => 'Merchant and storefront uploads',
                'retention' => 'excluded from retention',
                'protected' => true,
            ],
        ];
    }
}
