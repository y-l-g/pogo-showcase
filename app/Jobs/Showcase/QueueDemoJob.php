<?php

declare(strict_types=1);

namespace App\Jobs\Showcase;

use App\Services\QueueShowcase\QueueBoard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Sleep;
use Throwable;

#[Tries(1)]
final class QueueDemoJob implements ShouldQueue
{
    use Queueable;

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

            Sleep::usleep($this->durationMs * 1000);

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
        resolve(QueueBoard::class)->markFailed($this->batchId, $this->jobId, $e->getMessage());
    }

    private function workerLane(): int
    {
        $workers = max(1, (int) config('queue.connections.pogo.threads', 4));

        return (abs(crc32($this->jobId)) % $workers) + 1;
    }
}
