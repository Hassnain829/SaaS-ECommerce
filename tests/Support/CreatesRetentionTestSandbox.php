<?php

namespace Tests\Support;

use App\Support\ProjectHygiene\ProjectCleanupService;
use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectRetentionService;
use App\Support\ProjectHygiene\ProjectStorageProtection;
use App\Support\ProjectHygiene\RetentionTestSandbox;
use Illuminate\Support\Facades\File;

/**
 * Centralized isolated sandbox for destructive retention/cleanup tests.
 *
 * Never call project:retention --force or project:cleanup --force against base_path()
 * or storage_path(). Always create a marked sandbox outside the repository.
 */
trait CreatesRetentionTestSandbox
{
    protected function createRetentionTestSandbox(): string
    {
        return RetentionTestSandbox::createRoot();
    }

    protected function assertRetentionSandboxRoot(string $path): void
    {
        RetentionTestSandbox::assertValidSandboxRoot($path);
    }

    protected function retentionServiceForSandbox(string $sandboxRoot): ProjectRetentionService
    {
        $this->assertRetentionSandboxRoot($sandboxRoot);
        $paths = ProjectPathGuard::forProject($sandboxRoot);

        return new ProjectRetentionService($paths, new ProjectStorageProtection($paths));
    }

    protected function cleanupServiceForSandbox(string $sandboxRoot): ProjectCleanupService
    {
        $this->assertRetentionSandboxRoot($sandboxRoot);
        $paths = ProjectPathGuard::forProject($sandboxRoot);

        return new ProjectCleanupService($paths);
    }

    /**
     * @param  callable(string, ProjectRetentionService): void  $callback
     */
    protected function withRetentionSandbox(callable $callback): void
    {
        $root = $this->createRetentionTestSandbox();

        try {
            $callback($root, $this->retentionServiceForSandbox($root));
        } finally {
            if (is_dir($root)) {
                File::deleteDirectory($root);
            }
        }
    }

    /**
     * @param  callable(string, ProjectCleanupService): void  $callback
     */
    protected function withCleanupSandbox(callable $callback): void
    {
        $root = $this->createRetentionTestSandbox();

        try {
            $callback($root, $this->cleanupServiceForSandbox($root));
        } finally {
            if (is_dir($root)) {
                File::deleteDirectory($root);
            }
        }
    }
}
