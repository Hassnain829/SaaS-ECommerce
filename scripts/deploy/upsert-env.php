<?php

/**
 * Upsert KEY=value into .env. Value is read from the process environment.
 * Usage: php scripts/deploy/upsert-env.php KEY [ENV_FILE]
 */
$key = $argv[1] ?? '';
$file = $argv[2] ?? dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env';

if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
    fwrite(STDERR, "Invalid env key\n");
    exit(1);
}

$value = getenv($key);
if (! is_string($value) || trim($value) === '') {
    echo "skip {$key} (not provided)\n";
    exit(0);
}

if (! is_file($file) || ! is_writable($file)) {
    fwrite(STDERR, "Cannot write env file\n");
    exit(1);
}

$contents = file_get_contents($file);
if ($contents === false) {
    fwrite(STDERR, "Cannot read env file\n");
    exit(1);
}

$quoted = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
$line = $key.'='.$quoted;
$pattern = '/^'.preg_quote($key, '/').'=.*/m';

if (preg_match($pattern, $contents) === 1) {
    $contents = (string) preg_replace($pattern, $line, $contents, 1);
} else {
    $contents = rtrim($contents)."\n".$line."\n";
}

if (file_put_contents($file, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Cannot write env file\n");
    exit(1);
}

echo "upserted {$key}\n";
