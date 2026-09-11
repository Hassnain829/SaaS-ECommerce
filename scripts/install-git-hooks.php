<?php

declare(strict_types=1);

/**
 * Point this clone at committed hooks under .githooks/ (Pint pre-push, etc.).
 * Safe to run repeatedly. No-op outside a git checkout.
 */
$root = dirname(__DIR__);

if (! is_dir($root.DIRECTORY_SEPARATOR.'.git') && ! is_file($root.DIRECTORY_SEPARATOR.'.git')) {
    return;
}

$hooksPath = $root.DIRECTORY_SEPARATOR.'.githooks';
if (! is_dir($hooksPath)) {
    return;
}

$prePush = $hooksPath.DIRECTORY_SEPARATOR.'pre-push';
if (is_file($prePush) && PHP_OS_FAMILY !== 'Windows') {
    @chmod($prePush, 0755);
}

$git = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse --is-inside-work-tree 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
if ($git !== 'true') {
    return;
}

$current = trim((string) shell_exec('git -C '.escapeshellarg($root).' config --get core.hooksPath 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
if ($current === '.githooks') {
    return;
}

passthru('git -C '.escapeshellarg($root).' config core.hooksPath .githooks', $code);
if ($code === 0) {
    fwrite(STDOUT, "Git hooks path set to .githooks (Pint pre-push enabled).\n");
}
