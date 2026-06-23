<?php

$archive = $argv[1] ?? null;
if ($archive === null || ! is_file($archive)) {
    fwrite(STDERR, "Usage: php scripts/inspect_source_archive.php <path-to-zip>\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($archive) !== true) {
    fwrite(STDERR, "Unable to open archive.\n");
    exit(1);
}

$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (is_string($name)) {
        $entries[] = str_replace('\\', '/', $name);
    }
}
$zip->close();

sort($entries);

$required = [
    '.env.example',
    'dev-test-storefront/.env.example',
    'bootstrap/cache/.gitignore',
    'storage/logs/.gitignore',
    'storage/framework/cache/.gitignore',
    'storage/framework/cache/data/.gitignore',
    'storage/framework/sessions/.gitignore',
    'storage/framework/views/.gitignore',
    'storage/app/.gitignore',
    'composer.json',
    'README.md',
    'AGENTS.md',
];

echo 'Archive: '.$archive.PHP_EOL;
echo 'Size: '.filesize($archive).' bytes'.PHP_EOL;
echo 'File count: '.count($entries).PHP_EOL.PHP_EOL;

foreach ($required as $path) {
    echo ($path.': '.(in_array($path, $entries, true) ? 'PRESENT' : 'MISSING')).PHP_EOL;
}

echo '.env: '.(in_array('.env', $entries, true) ? 'PRESENT (BAD)' : 'ABSENT (good)').PHP_EOL;
echo 'vendor/ entries: '.count(array_filter($entries, fn (string $e): bool => str_starts_with($e, 'vendor/'))).PHP_EOL;
echo 'node_modules/ entries: '.count(array_filter($entries, fn (string $e): bool => str_starts_with($e, 'node_modules/'))).PHP_EOL;
