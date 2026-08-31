<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$fatalLog = __DIR__.'/storage/logs/php-fatal.log';

register_shutdown_function(static function () use ($fatalLog): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (! in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $line = sprintf(
        "[%s] FATAL %s in %s:%d\n",
        date('c'),
        $error['message'],
        $error['file'],
        $error['line']
    );

    @file_put_contents($fatalLog, $line, FILE_APPEND);

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo "Laravel bootstrap failed.\n";
    echo $error['message']."\n";
    echo $error['file'].':'.$error['line']."\n";
});

try {
    if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require __DIR__.'/vendor/autoload.php';

    /** @var Application $app */
    $app = require_once __DIR__.'/bootstrap/app.php';

    $app->handleRequest(Request::capture());
} catch (Throwable $e) {
    @file_put_contents(
        $fatalLog,
        sprintf("[%s] EXCEPTION %s in %s:%d\n%s\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()),
        FILE_APPEND
    );

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo "Laravel exception.\n";
    echo $e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
