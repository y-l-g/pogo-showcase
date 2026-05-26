<?php

declare(strict_types=1);

use App\Jobs\Showcase\QueueDemoJob;
use App\Models\User;
use App\Services\QueueShowcase\QueueBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the queue showcase page', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('showcase.queue'))
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('showcase/Queue')
                ->where('queueAvailable', false)
                ->where('queueStats.current_depth', 0)
                ->where('workerCount', 4)
                ->where('batch.status', 'idle')
                ->where('batch.total', 0)
        );
});

it('normalizes native queue status for the queue showcase page', function (): void {
    $GLOBALS['pogo_queue_status_payload'] = [
        'ready' => true,
        'queues' => [[
            'queue' => 'default',
            'ready' => true,
            'pending' => 2,
            'delayed' => 1,
            'reserved' => 1,
            'enqueued' => 12,
            'reserved_total' => 9,
            'dropped_full' => 1,
            'dropped_payload_too_large' => 2,
            'dropped_shutdown' => 3,
            'backend_errors' => 4,
            'max_payload_bytes' => 1024,
        ]],
    ];

    $user = User::factory()->create();

    actingAs($user)
        ->get(route('showcase.queue'))
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('queueAvailable', true)
                ->where('queueStats.enqueued', 12)
                ->where('queueStats.dispatched', 9)
                ->where('queueStats.current_depth', 4)
                ->where('queueStats.dropped_full', 1)
                ->where('queueStats.dropped_payload_too_large', 2)
                ->where('queueStats.dropped_shutdown', 3)
                ->where('queueStats.send_errors', 4)
                ->where('queueStats.max_message_bytes', 1024)
        );
});

it('creates a queue demo batch and dispatches jobs', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'))
        ->assertSessionHas('success', 'Queued 8 demo jobs.');

    Queue::assertPushed(QueueDemoJob::class, 8);

    $batch = resolve(QueueBoard::class)->current();

    expect($batch['status'])->toBe('active')
        ->and($batch['total'])->toBe(8)
        ->and($batch['queued'])->toBe(8)
        ->and($batch['running'])->toBe(0)
        ->and($batch['completed'])->toBe(0);
});

it('dispatches demo jobs through the native v2 queue function', function (): void {
    $GLOBALS['pogo_queue_push_calls'] = [];

    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'))
        ->assertSessionHas('success', 'Queued 8 demo jobs.');

    expect($GLOBALS['pogo_queue_push_calls'])->toHaveCount(8)
        ->and($GLOBALS['pogo_queue_push_calls'][0]['queue'])->toBe('default')
        ->and($GLOBALS['pogo_queue_push_calls'][0]['delay'])->toBe(0);

    $payload = json_decode((string) $GLOBALS['pogo_queue_push_calls'][0]['payload'], true);

    expect($payload)->toBeArray()
        ->and($payload['displayName'] ?? null)->toBe(QueueDemoJob::class);
});

it('does not dispatch a second batch while one is active', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'));

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'))
        ->assertSessionHas('error', 'A queue demo batch is already running.');

    Queue::assertPushed(QueueDemoJob::class, 8);
});

it('clears the queue demo board', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'));

    actingAs($user)
        ->post(route('showcase.queue.reset'))
        ->assertRedirect(route('showcase.queue'))
        ->assertSessionHas('success', 'Queue board reset.');

    expect(resolve(QueueBoard::class)->current()['status'])->toBe('idle');
});

it('marks queue demo jobs as completed', function (): void {
    $board = resolve(QueueBoard::class);
    $batch = $board->start([[
        'id' => 'demo',
        'label' => 'Demo job',
        'detail' => 'Test task',
        'duration_ms' => 1,
        'icon' => 'i-lucide-play',
    ]]);

    $job = new QueueDemoJob((string) $batch['id'], 'demo', 1, 'Demo job');
    $job->handle($board);

    $current = $board->current();

    expect($current['status'])->toBe('finished')
        ->and($current['completed'])->toBe(1)
        ->and($current['jobs'][0]['status'])->toBe('completed')
        ->and($current['jobs'][0]['worker_lane'])->toBeInt()
        ->and($current['jobs'][0]['result'])->toBe('Demo job finished in 0.0s');
});

afterEach(function (): void {
    unset($GLOBALS['pogo_queue_push_calls']);
    unset($GLOBALS['pogo_queue_status_payload']);
});

if (! function_exists('pogo_queue')) {
    function pogo_queue(string $data): int
    {
        return 1;
    }
}

if (! function_exists('pogo_queue_push')) {
    function pogo_queue_push(string $queue, string $payload, int $delaySeconds = 0): string
    {
        $GLOBALS['pogo_queue_push_calls'][] = [
            'queue' => $queue,
            'payload' => $payload,
            'delay' => $delaySeconds,
        ];

        return json_encode([
            'ok' => true,
            'id' => 'test-delivery-'.count($GLOBALS['pogo_queue_push_calls']),
            'code' => 1,
        ], JSON_THROW_ON_ERROR);
    }
}

if (! function_exists('pogo_queue_status')) {
    function pogo_queue_status(?string $queue = null): string
    {
        return json_encode($GLOBALS['pogo_queue_status_payload'] ?? [
            'ready' => false,
            'queues' => [],
        ], JSON_THROW_ON_ERROR);
    }
}
