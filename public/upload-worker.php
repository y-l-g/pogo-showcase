<?php

declare(strict_types=1);

use App\Services\UploadShowcase\UploadShowcase;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\Worker;

if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';
    exit(1);
}

$basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: dirname(__DIR__);

if (! file_exists($basePath.'/bootstrap/app.php')) {
    error_log("Application path not found at: $basePath");
    exit(1);
}

require_once $basePath.'/vendor/autoload.php';

$worker = tap(new Worker(
    new ApplicationFactory($basePath),
    new FrankenPhpClient
))->boot();

try {
    while (frankenphp_handle_request(static function (mixed $payload) use ($worker): string {
        try {
            if (is_string($payload)) {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            }

            throw_unless(is_array($payload), RuntimeException::class, 'Invalid upload event payload.');

            $uploadId = $payload['upload_id'] ?? null;
            $metadata = $payload['metadata'] ?? [];
            $userId = is_array($metadata) ? ($metadata['user_id'] ?? null) : null;

            throw_unless(is_string($uploadId) && $uploadId !== '', RuntimeException::class, 'Upload event is missing an upload id.');
            throw_unless(is_string($userId) && $userId !== '', RuntimeException::class, 'Upload event is missing user metadata.');

            $worker->application()['cache']->store()->put(
                UploadShowcase::eventCacheKey($userId, $uploadId),
                $payload,
                now()->addMinutes(30)
            );

            return json_encode(['ok' => true], JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            report($e);

            return json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
    })) {
        gc_collect_cycles();
    }
} finally {
    $worker->terminate();
}
