<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Jobs\Pogo\WaitJob;
use App\Services\PogoShowcase\PogoDispatcher;
use Illuminate\Http\JsonResponse;

final readonly class LandingParallelController
{
    /**
     * @var array<int, array{label: string, duration_ms: int}>
     */
    private const TASKS = [
        ['label' => 'Fetch reviews', 'duration_ms' => 120],
        ['label' => 'Check stock', 'duration_ms' => 180],
        ['label' => 'Quote shipping', 'duration_ms' => 240],
    ];

    public function __invoke(PogoDispatcher $pogo): JsonResponse
    {
        $startedAt = hrtime(true);
        $pogoAvailable = $pogo->available();
        $jobs = $pogoAvailable
            ? $this->runPogo($pogo)
            : $this->runFallback();

        return response()->json([
            'mode' => $pogoAvailable ? 'pogo_parallel' : 'php_fallback',
            'elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'jobs' => $jobs,
        ]);
    }

    /**
     * @return array<int, array{label: string, duration_ms: int}>
     */
    private function runPogo(PogoDispatcher $pogo): array
    {
        $handles = [];

        foreach (self::TASKS as $task) {
            $handles[] = $pogo->dispatch(WaitJob::class, $task, 'default');
        }

        return array_map(
            fn (int $handle): array => $this->unwrap($pogo->await($handle, 3.0)),
            $handles
        );
    }

    /**
     * @return array<int, array{label: string, duration_ms: int}>
     */
    private function runFallback(): array
    {
        $job = new WaitJob;

        return array_map(
            static fn (array $task): array => $job->handle($task),
            self::TASKS
        );
    }

    /**
     * @return array{label: string, duration_ms: int}
     */
    private function unwrap(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        if (is_array($value) && ($value['ok'] ?? null) === true && is_array($value['result'] ?? null)) {
            $value = $value['result'];
        }

        return is_array($value) ? [
            'label' => (string) ($value['label'] ?? 'Task'),
            'duration_ms' => (int) ($value['duration_ms'] ?? 0),
        ] : [
            'label' => 'Task',
            'duration_ms' => 0,
        ];
    }
}
