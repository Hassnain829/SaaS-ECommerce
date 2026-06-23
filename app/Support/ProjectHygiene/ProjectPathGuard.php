<?php

namespace App\Support\ProjectHygiene;

use RuntimeException;

final class ProjectPathGuard
{
    /** @var list<string> */
    public const PROTECTED_RELATIVE_PATHS = [
        '.git',
    ];

    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public static function forProject(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? base_path();
        $resolved = realpath($root);

        if ($resolved === false) {
            throw new RuntimeException('Project root does not exist.');
        }

        return new self(str_replace('\\', '/', $resolved));
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function resolve(string $relativeOrAbsolutePath): string
    {
        $absolute = $this->toAbsolutePath($relativeOrAbsolutePath);

        return $this->canonicalize($absolute);
    }

    public function isWithinProject(string $path): bool
    {
        try {
            $resolved = $this->resolve($path);
        } catch (RuntimeException) {
            return false;
        }

        return $this->isUnderRoot($resolved);
    }

    public function assertWithinProject(string $path): void
    {
        if (! $this->isWithinProject($path)) {
            throw new RuntimeException('Refusing to operate on a path outside the project root.');
        }
    }

    public function assertSafeDeletionTarget(string $path): void
    {
        $this->assertWithinProject($path);

        $resolved = $this->resolve($path);
        $relative = $this->toRelativePath($resolved);

        if ($relative === '' || $relative === '.') {
            throw new RuntimeException('Refusing to delete the project root.');
        }

        foreach (self::PROTECTED_RELATIVE_PATHS as $protected) {
            if ($relative === $protected || str_starts_with($relative, $protected.'/')) {
                throw new RuntimeException('Refusing to delete protected project path: '.$protected);
            }
        }
    }

    private function toAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($this->isAbsolutePath($path)) {
            return $this->lexicalNormalize($path);
        }

        return $this->lexicalNormalize(rtrim($this->projectRoot, '/').'/'.ltrim($path, '/'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('/^[A-Za-z]:\//', $path)
            || (bool) preg_match('/^[A-Za-z]:$/', $path);
    }

    private function lexicalNormalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        $drive = '';
        if (preg_match('/^([A-Za-z]:)(\/.*)?$/', $path, $matches)) {
            $drive = $matches[1];
            $path = $matches[2] ?? '/';
        }

        $leadingSlash = str_starts_with($path, '/') ? '/' : '';
        $segments = explode('/', trim($path, '/'));
        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($stack === []) {
                    throw new RuntimeException('Path traversal rejected.');
                }

                array_pop($stack);

                continue;
            }

            $stack[] = $segment;
        }

        $normalized = $leadingSlash.implode('/', $stack);

        return $drive.$normalized;
    }

    private function canonicalize(string $absolutePath): string
    {
        $absolutePath = $this->lexicalNormalize($absolutePath);

        if (file_exists($absolutePath)) {
            $real = realpath($absolutePath);

            if ($real === false) {
                throw new RuntimeException('Unable to resolve existing path.');
            }

            $real = str_replace('\\', '/', $real);

            if (! $this->isUnderRoot($real)) {
                throw new RuntimeException('Symlink or path escapes project root.');
            }

            return $real;
        }

        $parent = dirname($absolutePath);
        $basename = basename($absolutePath);

        if ($parent === $absolutePath || $basename === '') {
            if (! $this->isUnderRoot($absolutePath)) {
                throw new RuntimeException('Path resolves outside project root.');
            }

            return $absolutePath;
        }

        $canonicalParent = (is_dir($parent) || is_file($parent))
            ? $this->canonicalize($parent)
            : $this->canonicalizeNonExistingParent($parent);

        $candidate = rtrim($canonicalParent, '/').'/'.$basename;

        if (! $this->isUnderRoot($candidate)) {
            throw new RuntimeException('Path resolves outside project root.');
        }

        return $candidate;
    }

    private function canonicalizeNonExistingParent(string $path): string
    {
        $path = $this->lexicalNormalize($path);
        $parent = dirname($path);
        $basename = basename($path);

        if ($parent === $path || $basename === '') {
            if (! $this->isUnderRoot($path)) {
                throw new RuntimeException('Path resolves outside project root.');
            }

            return $path;
        }

        if (is_dir($parent)) {
            return $this->canonicalize($parent);
        }

        $resolvedGrandparent = $this->canonicalizeNonExistingParent($parent);

        return rtrim($resolvedGrandparent, '/').'/'.$basename;
    }

    private function isUnderRoot(string $path): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/').'/';
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/').'/';

        if (PHP_OS_FAMILY === 'Windows') {
            return str_starts_with(strtolower($path), strtolower($root));
        }

        return str_starts_with($path, $root);
    }

    private function toRelativePath(string $absolutePath): string
    {
        $absolutePath = rtrim(str_replace('\\', '/', $absolutePath), '/');
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            if (str_starts_with(strtolower($absolutePath), strtolower($root.'/')) || strtolower($absolutePath) === strtolower($root)) {
                return ltrim(substr($absolutePath, strlen($root)), '/');
            }
        } elseif (str_starts_with($absolutePath, $root.'/') || $absolutePath === $root) {
            return ltrim(substr($absolutePath, strlen($root)), '/');
        }

        return ltrim($absolutePath, '/');
    }
}
