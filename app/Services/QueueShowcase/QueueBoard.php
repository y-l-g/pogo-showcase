<?php

declare(strict_types=1);

namespace App\Services\QueueShowcase;

use Illuminate\Support\Facades\Cache;

final class QueueBoard
{
    private const CACHE_KEY = 'queue_showcase.batch';

    private const LOCK_KEY = 'queue_showcase.lock';

    private const CACHE_TTL_SECONDS = 1800;

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $batch = Cache::get(self::CACHE_KEY);

        return $this->snapshot(is_array($batch) ? $batch : $this->emptyBatch());
    }

    /**
     * @param  array<int, array{id: string, label: string, detail: string, duration_ms: int, icon: string}>  $jobs
     * @return array<string, mixed>
     */
    public function start(array $jobs): array
    {
        $batchId = (string) str()->uuid();
        $queuedAt = now()->toISOString();

        $batch = [
            'id' => $batchId,
            'created_at' => $queuedAt,
            'jobs' => array_map(
                static fn (array $job): array => $job + [
                    'status' => 'queued',
                    'worker_lane' => null,
                    'queued_at' => $queuedAt,
                    'started_at' => null,
                    'finished_at' => null,
                    'result' => null,
                    'error' => null,
                ],
                $jobs
            ),
        ];

        Cache::put(self::CACHE_KEY, $batch, self::CACHE_TTL_SECONDS);

        return $this->snapshot($batch);
    }

    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function markRunning(string $batchId, string $jobId, int $workerLane): void
    {
        $this->mutate($batchId, $jobId, static fn (array $job): array => array_merge($job, [
            'status' => 'running',
            'worker_lane' => $workerLane,
            'started_at' => now()->toISOString(),
            'finished_at' => null,
            'error' => null,
        ]));
    }

    public function markCompleted(string $batchId, string $jobId, string $result): void
    {
        $this->mutate($batchId, $jobId, static fn (array $job): array => array_merge($job, [
            'status' => 'completed',
            'finished_at' => now()->toISOString(),
            'result' => $result,
            'error' => null,
        ]));
    }

    public function markFailed(string $batchId, string $jobId, string $error): void
    {
        $this->mutate($batchId, $jobId, static fn (array $job): array => array_merge($job, [
            'status' => 'failed',
            'finished_at' => now()->toISOString(),
            'error' => $error,
        ]));
    }

    /**
     * @return array<int, array{id: string, label: string, detail: string, duration_ms: int, icon: string}>
     */
    public function demoJobs(): array
    {
        return [
            ['id' => 'thumbnail', 'label' => 'Generate thumbnail', 'detail' => 'Media pipeline', 'duration_ms' => 900, 'icon' => 'i-lucide-image'],
            ['id' => 'receipt', 'label' => 'Send receipt', 'detail' => 'Transactional email', 'duration_ms' => 1300, 'icon' => 'i-lucide-mail'],
            ['id' => 'analytics', 'label' => 'Aggregate analytics', 'detail' => 'Event rollup', 'duration_ms' => 1700, 'icon' => 'i-lucide-chart-no-axes-column'],
            ['id' => 'invoice', 'label' => 'Create invoice', 'detail' => 'PDF rendering', 'duration_ms' => 2100, 'icon' => 'i-lucide-file-text'],
            ['id' => 'webhook', 'label' => 'Deliver webhook', 'detail' => 'Partner callback', 'duration_ms' => 1200, 'icon' => 'i-lucide-radio-tower'],
            ['id' => 'index', 'label' => 'Refresh search index', 'detail' => 'Catalog sync', 'duration_ms' => 2600, 'icon' => 'i-lucide-search'],
            ['id' => 'export', 'label' => 'Build CSV export', 'detail' => 'Reporting task', 'duration_ms' => 3000, 'icon' => 'i-lucide-table'],
            ['id' => 'cleanup', 'label' => 'Clean temporary files', 'detail' => 'Storage hygiene', 'duration_ms' => 1500, 'icon' => 'i-lucide-trash-2'],
        ];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    private function mutate(string $batchId, string $jobId, callable $callback): void
    {
        Cache::lock(self::LOCK_KEY, 5)->block(2, function () use ($batchId, $jobId, $callback): void {
            $batch = Cache::get(self::CACHE_KEY);

            if (! is_array($batch) || ($batch['id'] ?? null) !== $batchId) {
                return;
            }

            $jobs = array_map(
                static fn (array $job): array => ($job['id'] ?? null) === $jobId ? $callback($job) : $job,
                is_array($batch['jobs'] ?? null) ? $batch['jobs'] : []
            );

            $batch['jobs'] = $jobs;

            Cache::put(self::CACHE_KEY, $batch, self::CACHE_TTL_SECONDS);
        });
    }

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, mixed>
     */
    private function snapshot(array $batch): array
    {
        $jobs = is_array($batch['jobs'] ?? null) ? array_values($batch['jobs']) : [];
        $counts = [
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? 'queued');

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $total = count($jobs);
        $active = $counts['queued'] > 0 || $counts['running'] > 0;

        return [
            'id' => $batch['id'] ?? null,
            'status' => $total === 0 ? 'idle' : ($active ? 'active' : 'finished'),
            'created_at' => $batch['created_at'] ?? null,
            'total' => $total,
            'queued' => $counts['queued'],
            'running' => $counts['running'],
            'completed' => $counts['completed'],
            'failed' => $counts['failed'],
            'jobs' => $jobs,
        ];
    }

    /**
     * @return array{id: null, created_at: null, jobs: array<int, never>}
     */
    private function emptyBatch(): array
    {
        return [
            'id' => null,
            'created_at' => null,
            'jobs' => [],
        ];
    }
}
