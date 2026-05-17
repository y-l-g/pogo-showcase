<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Jobs\Showcase\QueueDemoJob;
use App\Services\QueueShowcase\QueueBoard;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class RunQueueDemoController extends Controller
{
    public function __invoke(QueueBoard $board): RedirectResponse
    {
        $current = $board->current();

        if (($current['status'] ?? null) === 'active') {
            return to_route('showcase.queue')
                ->with('error', 'A queue demo batch is already running.');
        }

        $batch = $board->start($board->demoJobs());
        $queueName = (string) config('queue.connections.pogo.queue', 'default');

        try {
            foreach ($batch['jobs'] as $job) {
                dispatch(new QueueDemoJob((string) $batch['id'], (string) $job['id'], (int) $job['duration_ms'], (string) $job['label']))->onConnection('pogo')->onQueue($queueName);
            }
        } catch (Throwable $e) {
            $board->reset();

            return to_route('showcase.queue')
                ->with('error', 'Queue demo failed to dispatch: '.$e->getMessage());
        }

        return to_route('showcase.queue')
            ->with('success', sprintf('Queued %d demo jobs.', $batch['total']));
    }
}
