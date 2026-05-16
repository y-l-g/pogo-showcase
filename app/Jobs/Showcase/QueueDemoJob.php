<?php

declare(strict_types=1);

namespace App\Jobs\Showcase;

use App\Services\QueueShowcase\QueueBoard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class QueueDemoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly string $batchId,
        private readonly string $jobId,
        private readonly int $durationMs,
        private readonly string $label,
    ) {}

    public function handle(QueueBoard $board): void
    {
        try {
            $board->markRunning($this->batchId, $this->jobId, $this->workerLane());

            usleep($this->durationMs * 1000);

            $board->markCompleted(
                $this->batchId,
                $this->jobId,
                sprintf('%s finished in %.1fs', $this->label, $this->durationMs / 1000)
            );
        } catch (Throwable $e) {
            $board->markFailed($this->batchId, $this->jobId, $e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        app(QueueBoard::class)->markFailed($this->batchId, $this->jobId, $e->getMessage());
    }

    private function workerLane(): int
    {
        $workers = max(1, (int) env('POGO_QUEUE_THREADS', 4));

        return (abs(crc32($this->jobId)) % $workers) + 1;
    }
}
