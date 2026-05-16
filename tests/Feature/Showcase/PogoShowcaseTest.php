<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\PogoShowcase\PogoDispatcher;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the pogo showcase page', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('showcase.pogo'))
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('showcase/Pogo')
                ->where('pogoAvailable', false)
                ->where('poolSizes.default', 0)
                ->where('poolSizes.external_api', 0)
        );
});

it('redirects with an error when pogo is unavailable', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.pogo.run'), [
            'sku' => 'POGO-001',
            'quantity' => 3,
        ])
        ->assertRedirect(route('showcase.pogo'))
        ->assertSessionHas('error', 'The Pogo extension is not loaded in this PHP runtime.');
});

it('stores the latest result when pogo jobs complete', function (): void {
    app()->instance(PogoDispatcher::class, new class extends PogoDispatcher
    {
        private int $nextHandle = 1;

        public function available(): bool
        {
            return true;
        }

        public function poolSize(string $pool = 'default'): int
        {
            return $pool === 'external_api' ? 8 : 4;
        }

        public function dispatch(string $class, array $args = [], string $pool = 'default'): int
        {
            return $this->nextHandle++;
        }

        public function await(int $handle, float $timeout = 5.0): mixed
        {
            return [
                'handle' => $handle,
                'ok' => true,
            ];
        }
    });

    $user = User::factory()->create();

    $response = actingAs($user)
        ->post(route('showcase.pogo.run'), [
            'sku' => 'pogo-123',
            'quantity' => 4,
        ])
        ->assertRedirect(route('showcase.pogo'))
        ->assertSessionHas('success');

    $result = $response->baseResponse->getSession()->get('pogo_demo_result');

    expect($result['sku'])->toBe('POGO-123')
        ->and($result['quantity'])->toBe(4)
        ->and($result['pool'])->toBe('external_api')
        ->and($result['workers'])->toBe(8)
        ->and($result['jobs'])->toHaveCount(3)
        ->and($result['sequential_estimate_ms'])->toBe(1340);
});
