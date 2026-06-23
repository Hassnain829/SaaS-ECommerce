<?php

namespace App\Support\ProjectHygiene;

final class ProjectStorageProtection
{
    /** @var list<string> */
    public const PROTECTED_SUBPATHS = [
        '/labels/',
        '/uploads/',
        '/00_documents/',
        '/printed_scans/',
    ];

    /** @var list<string> */
    public const PROTECTION_MARKER_FILES = [
        '.protected',
        'evidence-manifest.json',
    ];

    /** @var list<string> */
    private const PROTECTED_FILENAME_PATTERNS = [
        '/^fedex-validation-final-.*\.zip$/i',
    ];

    public function __construct(
        private readonly ProjectPathGuard $paths,
    ) {}

    public function isProtected(string $absolutePath): bool
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $basename = basename($normalized);

        if ($basename === '.gitignore') {
            return true;
        }

        if (in_array($basename, self::PROTECTION_MARKER_FILES, true)) {
            return true;
        }

        if (ProjectTrackedFiles::isTracked($this->paths->projectRoot(), $normalized)) {
            return true;
        }

        $relative = ProjectTrackedFiles::toRelativePath($this->paths->projectRoot(), $normalized);

        if (in_array($relative, ProjectCleanupService::PLACEHOLDER_GITIGNORE_PATHS, true)) {
            return true;
        }

        foreach (self::PROTECTED_SUBPATHS as $subpath) {
            if (str_contains($relative, trim($subpath, '/'))) {
                return true;
            }
        }

        foreach (self::PROTECTED_FILENAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $basename) === 1) {
                return true;
            }
        }

        return $this->hasAncestorProtectionMarker($normalized);
    }

    private function hasAncestorProtectionMarker(string $absolutePath): bool
    {
        $directory = is_dir($absolutePath) ? $absolutePath : dirname($absolutePath);
        $root = rtrim($this->paths->projectRoot(), '/');

        while ($directory !== $root && str_starts_with($directory, $root)) {
            foreach (self::PROTECTION_MARKER_FILES as $marker) {
                $markerPath = $directory.'/'.$marker;
                if (is_file($markerPath)) {
                    return true;
                }
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return false;
    }
}
