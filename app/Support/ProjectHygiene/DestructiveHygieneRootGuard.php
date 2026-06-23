<?php

namespace App\Support\ProjectHygiene;

final class DestructiveHygieneRootGuard
{
    public static function assertAllowed(ProjectPathGuard $paths, bool $force, bool $dryRun): void
    {
        if (! $force || $dryRun) {
            return;
        }

        if (! app()->environment('testing')) {
            return;
        }

        if (! config('project_retention.test_sandbox_required', true)) {
            return;
        }

        $root = str_replace('\\', '/', $paths->projectRoot());

        if (RetentionTestSandbox::isRealWorktreePath($root)) {
            throw UnsafeRetentionTestRootException::blocked(
                'Destructive hygiene is blocked in testing against the real application worktree. '
                .'Use an isolated directory outside the repository with a .retention-test-sandbox marker. '
                .'No files were changed.',
            );
        }

        if (! RetentionTestSandbox::hasValidMarker($root)) {
            throw UnsafeRetentionTestRootException::blocked(
                'Destructive hygiene is blocked in testing because the root is not a marked retention sandbox. '
                .'Create a sandbox with .retention-test-sandbox before running --force. '
                .'No files were changed.',
            );
        }
    }
}
