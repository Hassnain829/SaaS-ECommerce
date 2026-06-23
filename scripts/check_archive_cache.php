<?php

$zip = sys_get_temp_dir().'/archive-check.zip';
passthru('git archive --worktree-attributes --format=zip --output='.escapeshellarg($zip).' HEAD', $code);
$z = new ZipArchive;
$z->open($zip);
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (str_contains($n, 'framework/cache')) {
        echo $n, PHP_EOL;
    }
}
