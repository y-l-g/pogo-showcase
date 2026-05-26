<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\QueueShowcase\QueueBoard;
use Inertia\Inertia;
use Inertia\Response;

final class ShowQueueController extends Controller
{
    public function __invoke(QueueBoard $board): Response
    {
        $stats = $this->queueStats();

        return Inertia::render('showcase/Queue', [
            'queueAvailable' => function_exists('pogo_queue') && ($stats['driver_ready'] ?? false) === true,
            'queueStats' => $stats,
            'workerCount' => max(1, (int) config('queue.connections.pogo.threads', 4)),
            'batch' => $board->current(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function queueStats(): array
    {
        $defaults = [
            'enqueued' => 0,
            'dispatched' => 0,
            'dropped_full' => 0,
            'dropped_payload_too_large' => 0,
            'dropped_shutdown' => 0,
            'send_errors' => 0,
            'current_depth' => 0,
            'max_message_bytes' => null,
            'driver_ready' => false,
        ];

        if (! function_exists('pogo_queue_status')) {
            return $defaults;
        }

        $decoded = json_decode((string) pogo_queue_status(), true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        if (array_key_exists('driver_ready', $decoded)) {
            return array_merge($defaults, $decoded);
        }

        $queue = $decoded['queues'][0] ?? [];
        if (! is_array($queue)) {
            $queue = [];
        }

        return array_merge($defaults, [
            'enqueued' => (int) ($queue['enqueued'] ?? 0),
            'dispatched' => (int) ($queue['reserved_total'] ?? 0),
            'dropped_full' => (int) ($queue['dropped_full'] ?? 0),
            'dropped_payload_too_large' => (int) ($queue['dropped_payload_too_large'] ?? 0),
            'dropped_shutdown' => (int) ($queue['dropped_shutdown'] ?? 0),
            'send_errors' => (int) ($queue['backend_errors'] ?? 0),
            'current_depth' => (int) (($queue['pending'] ?? 0) + ($queue['delayed'] ?? 0) + ($queue['reserved'] ?? 0)),
            'max_message_bytes' => $queue['max_payload_bytes'] ?? null,
            'driver_ready' => ($decoded['ready'] ?? false) === true && ($queue['ready'] ?? false) === true,
        ]);
    }
}
