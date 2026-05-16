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

it('creates a queue demo batch and dispatches jobs', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.queue.run'))
        ->assertRedirect(route('showcase.queue'))
        ->assertSessionHas('success', 'Queued 8 demo jobs.');

    Queue::assertPushed(QueueDemoJob::class, 8);

    $batch = app(QueueBoard::class)->current();

    expect($batch['status'])->toBe('active')
        ->and($batch['total'])->toBe(8)
        ->and($batch['queued'])->toBe(8)
        ->and($batch['running'])->toBe(0)
        ->and($batch['completed'])->toBe(0);
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

    expect(app(QueueBoard::class)->current()['status'])->toBe('idle');
});

it('marks queue demo jobs as completed', function (): void {
    $board = app(QueueBoard::class);
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
