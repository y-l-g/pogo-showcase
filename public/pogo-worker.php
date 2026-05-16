<?php

declare(strict_types=1);

use Pogo\JobInterface;

if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';
    exit(1);
}

$basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__);

require_once $basePath.'/vendor/autoload.php';

while (frankenphp_handle_request(static function (mixed $payload): string {
    try {
        if (is_string($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Pogo payload.');
        }

        $class = $payload['class'] ?? null;
        $args = $payload['args'] ?? [];

        if (! is_string($class) || ! class_exists($class)) {
            throw new RuntimeException('Invalid or unknown Pogo job class.');
        }

        if (! is_array($args)) {
            throw new RuntimeException('Pogo job args must be an array.');
        }

        $job = new $class();

        if (! $job instanceof JobInterface) {
            throw new RuntimeException('Pogo job must implement Pogo\\JobInterface.');
        }

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
