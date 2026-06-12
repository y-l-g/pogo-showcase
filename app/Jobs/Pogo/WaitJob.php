<?php

declare(strict_types=1);

namespace App\Jobs\Pogo;

final class WaitJob
{
    /**
     * @param  array{label?: string, duration_ms?: int}  $args
     * @return array{label: string, duration_ms: int}
     */
    public function handle(array $args): array
    {
        $durationMs = max(50, min(1000, (int) ($args['duration_ms'] ?? 250)));

        usleep($durationMs * 1000);

        return [
            'label' => (string) ($args['label'] ?? 'Task'),
            'duration_ms' => $durationMs,
        ];
    }
}
