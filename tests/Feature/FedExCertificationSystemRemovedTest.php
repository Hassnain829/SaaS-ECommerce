<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Architecture guard: FedEx Integrator certification/validation subsystem must be gone.
 */
class FedExCertificationSystemRemovedTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const SCAN_ROOTS = [
        'app',
        'resources/views',
        'resources/js',
        'resources/css',
        'resources/fedex',
        'scripts',
        'config',
        'routes',
        '.env.example',
        '.gitignore',
        '.gitattributes',
    ];

    /** @var list<string> */
    private const FORBIDDEN = [
        'Services\\Carriers\\FedEx\\Validation',
        'FedExValidationArtifact',
        'FedExValidationSubmissionSnapshot',
        'FedExValidationExternalApproval',
        'FedExValidationRegionalAccount',
        'FedExValidationEventContext',
        'FedExCapabilityRegistry',
        'fedex:validation-retire',
        'fedex-validation.php',
        'validation_only',
        'STATUS_VALIDATION_ONLY',
        'validationUs08Enabled',
        'brandingEvidenceDisplayNames',
        'branding_evidence_display_names',
        'approved_for_validation',
        'FedExUs09',
        'US09 fixture',
        'validation workspace',
        'locked test case',
        'Sweden passthrough',
        'sweden_passthrough',
        'FedEx_Integrator_Validation',
        'buildImageUpload',
        'tradeDocumentsUploadImagePath',
        'logoIsApprovedSource',
        'workspaceStatus',
        'IntegratorUS01',
        'IntegratorUS08',
        'IntegratorUS09',
        'IntegratorUS10',
    ];

    public function test_certification_subsystem_paths_are_gone(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('app/Services/Carriers/FedEx/Validation'));
        $this->assertDirectoryDoesNotExist(base_path('app/Http/Controllers/Carrier/Validation'));
        $this->assertDirectoryDoesNotExist(base_path('resources/fedex-validation'));
        $this->assertDirectoryDoesNotExist(storage_path('app/fedex-validation'));
        $this->assertFileDoesNotExist(base_path('routes/fedex-validation.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/FedExValidationRetireCommand.php'));
        $this->assertFileDoesNotExist(base_path('app/Services/Carriers/FedEx/Capabilities/FedExCapabilityRegistry.php'));
        $this->assertFileDoesNotExist(base_path('tests/Support/FedExUs09TempAssetFactory.php'));
        $this->assertFileDoesNotExist(base_path('scripts/summarize_diagnostic_preflight.py'));
        $this->assertFileDoesNotExist(base_path('resources/views/components/ui/operator-banner.blade.php'));
    }

    public function test_validation_tables_are_dropped(): void
    {
        foreach ([
            'fedex_validation_artifacts',
            'fedex_validation_external_approvals',
            'fedex_validation_regional_accounts',
            'fedex_validation_submission_snapshots',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Certification table still exists: {$table}");
        }
    }

    public function test_active_runtime_source_has_no_certification_symbols(): void
    {
        $violations = [];

        foreach (self::SCAN_ROOTS as $scope) {
            $absolute = base_path($scope);
            if (is_file($absolute)) {
                $this->scanFile($absolute, $violations);

                continue;
            }
            if (! is_dir($absolute)) {
                continue;
            }
            foreach (File::allFiles($absolute) as $file) {
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['php', 'js', 'css', 'json', 'blade.php', 'md', 'example', 'gitignore', 'gitattributes', 'py'], true)
                    && ! str_ends_with($file->getFilename(), '.blade.php')
                    && ! in_array($file->getFilename(), ['.env.example', '.gitignore', '.gitattributes'], true)) {
                    continue;
                }
                $this->scanFile($file->getPathname(), $violations);
            }
        }

        $this->assertSame([], $violations, "Active source still references certification residue:\n".implode("\n", $violations));
    }

    public function test_storage_app_has_no_certification_directories(): void
    {
        $root = storage_path('app');
        if (! is_dir($root)) {
            $this->assertTrue(true);

            return;
        }

        $forbiddenNameFragments = [
            'fedex-validation',
            'FedEx_Integrator_Validation',
            'tmp-fedex',
            'temp-fedex',
            'temp-diagnostic',
            'temp-zip-verify',
            'fedex-validation-review',
            'Customer_Facing_Screenshots',
        ];

        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            // Never flag production labels/ETD under private/fedex.
            if (str_starts_with($relative, 'private/fedex/')) {
                continue;
            }
            foreach ($forbiddenNameFragments as $needle) {
                if (stripos($relative, $needle) !== false) {
                    $violations[] = 'storage/app/'.$relative.' contains '.$needle;
                }
            }
        }

        $this->assertSame([], $violations, "Certification storage residue remains:\n".implode("\n", $violations));
    }

    /**
     * @param  list<string>  $violations
     */
    private function scanFile(string $path, array &$violations): void
    {
        $contents = File::get($path);
        foreach (self::FORBIDDEN as $needle) {
            if (str_contains($contents, $needle)) {
                $violations[] = $this->relative($path).' contains '.$needle;
            }
        }

        // Case keys US01-US10 only flagged outside deliberately archived docs/migrations.
        if (preg_match('/\bUS0[1-9]\b|\bUS10\b/', $contents)) {
            $relative = $this->relative($path);
            if (! str_starts_with($relative, 'database/migrations/')
                && ! str_starts_with($relative, 'docs/archive/')
                && ! str_contains($relative, 'FedExCertificationSystemRemovedTest')) {
                $violations[] = $relative.' contains US01-US10 style case key';
            }
        }
    }

    private function relative(string $absolute): string
    {
        return str_replace('\\', '/', substr($absolute, strlen(base_path()) + 1));
    }
}
