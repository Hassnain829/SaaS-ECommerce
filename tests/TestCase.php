<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Cached config ignores phpunit.xml env overrides; a dev `config:cache` from
        // another environment can freeze product_import.queue_connection=database and
        // leave imports stuck "queued" in tests. Drop cache files before bootstrapping.
        $cacheDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        foreach (['config.php', 'routes-v7.php'] as $file) {
            $path = $cacheDir.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
