<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;
use Pogo\JobInterface;

if ((! (Request::server('FRANKENPHP_WORKER') ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';
    exit(1);
}

$basePath = Request::server('APP_BASE_PATH') ?? Env::get('APP_BASE_PATH', dirname(__DIR__));

require_once $basePath.'/vendor/autoload.php';

while (frankenphp_handle_request(static function (mixed $payload): string {
    try {
        if (is_string($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        throw_unless(is_array($payload), RuntimeException::class, 'Invalid Pogo payload.');

        $class = $payload['class'] ?? null;
        $args = $payload['args'] ?? [];

        throw_if(! is_string($class) || ! class_exists($class), RuntimeException::class, 'Invalid or unknown Pogo job class.');

        throw_unless(is_array($args), RuntimeException::class, 'Pogo job args must be an array.');

        $job = new $class;

        throw_unless($job instanceof JobInterface, RuntimeException::class, 'Pogo job must implement Pogo\\JobInterface.');

        return json_encode(
            ['ok' => true, 'result' => $job->handle($args)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    } catch (Throwable $e) {
        return json_encode(
            ['ok' => false, 'error' => $e->getMessage()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }
})) {
    gc_collect_cycles();
}
