<?php

declare(strict_types=1);

use App\Jobs\Pogo\FetchDeliveryWindowJob;
use App\Jobs\Pogo\FetchInventoryJob;
use App\Jobs\Pogo\FetchPriceJob;
use Pogo\JobInterface;

it('returns json-compatible pricing data', function (): void {
    $job = new FetchPriceJob();

    $result = $job->handle([
        'sku' => 'pogo-001',
        'quantity' => 2,
        'delay_ms' => 0,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('pricing')
        ->and($result['sku'])->toBe('POGO-001')
        ->and(json_encode($result))->toBeString();
});

it('returns json-compatible inventory data', function (): void {
    $job = new FetchInventoryJob();

    $result = $job->handle([
        'sku' => 'pogo-001',
        'quantity' => 2,
        'delay_ms' => 0,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('inventory')
        ->and($result['can_fulfill'])->toBeBool()
        ->and(json_encode($result))->toBeString();
});

it('returns json-compatible delivery data', function (): void {
    $job = new FetchDeliveryWindowJob();

    $result = $job->handle([
        'sku' => 'pogo-001',
        'quantity' => 2,
        'delay_ms' => 0,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('delivery')
        ->and($result['eta_days'])->toBeGreaterThanOrEqual(1)
        ->and(json_encode($result))->toBeString();
});
