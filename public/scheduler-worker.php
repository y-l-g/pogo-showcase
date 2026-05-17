<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;

if (! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'wb'));
}
if (! defined('STDOUT')) {
    define('STDOUT', fopen('php://stdout', 'wb'));
}

set_time_limit(120);
ini_set('memory_limit', '512M');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

if ((! (Request::server('FRANKENPHP_WORKER') ?? false)) || ! function_exists('frankenphp_handle_request')) {
    exit(1);
}

ignore_user_abort(true);

$basePath = Request::server('APP_BASE_PATH') ?? Env::get('APP_BASE_PATH', dirname(__DIR__));
require_once $basePath.'/vendor/autoload.php';

$frankenPhpClient = new FrankenPhpClient;
$requestCount = 0;
$maxRequests = Env::get('MAX_REQUESTS', Request::server('MAX_REQUESTS') ?? 60);

$handleRequest = static function ($payload = null) use ($basePath): void {
    $_SERVER['argv'] = ['artisan', 'schedule:run'];
    $_SERVER['argc'] = 2;
    $_SERVER['PHP_SELF'] = 'artisan';
    $_SERVER['SCRIPT_NAME'] = 'artisan';
    $_SERVER['SCRIPT_FILENAME'] = 'artisan';

    $app = require $basePath.'/bootstrap/app.php';

    try {
        $kernel = $app->make(Kernel::class);
        $kernel->call('schedule:run');
    } catch (Throwable $e) {
        fwrite(STDERR, '[Scheduler] Error: '.$e->getMessage()."\n");
    } finally {
        unset($app, $kernel);
        gc_collect_cycles();
    }
};

while ($requestCount < $maxRequests && frankenphp_handle_request($handleRequest)) {
    $requestCount++;
}
