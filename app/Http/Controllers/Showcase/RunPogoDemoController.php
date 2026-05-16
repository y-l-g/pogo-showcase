<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Jobs\Pogo\FetchDeliveryWindowJob;
use App\Jobs\Pogo\FetchInventoryJob;
use App\Jobs\Pogo\FetchPriceJob;
use App\Services\PogoShowcase\PogoDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class RunPogoDemoController extends Controller
{
    public function __invoke(Request $request, PogoDispatcher $pogo): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if (! $pogo->available()) {
            return redirect()
                ->route('showcase.pogo')
                ->withInput()
                ->with('error', 'The Pogo extension is not loaded in this PHP runtime.');
        }

        $pool = 'external_api';
        $sku = strtoupper((string) $validated['sku']);
        $quantity = (int) $validated['quantity'];
        $tasks = $this->tasks($sku, $quantity);
        $handles = [];
        $startedAt = hrtime(true);

        try {
            foreach ($tasks as $task) {
                $handles[$task['key']] = $pogo->dispatch(
                    $task['class'],
                    $task['args'],
                    $pool
                );
            }

            $jobs = [];
            foreach ($tasks as $task) {
                $jobs[] = [
                    'key' => $task['key'],
                    'label' => $task['label'],
                    'expected_delay_ms' => $task['args']['delay_ms'],
                    'result' => $pogo->await($handles[$task['key']], 3.0),
                ];
            }

            $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $sequentialEstimateMs = array_sum(array_map(
                static fn (array $task): int => (int) $task['args']['delay_ms'],
                $tasks
            ));

            $request->session()->flash('pogo_demo_result', [
                'sku' => $sku,
                'quantity' => $quantity,
                'pool' => $pool,
                'workers' => $pogo->poolSize($pool),
                'elapsed_ms' => $elapsedMs,
                'sequential_estimate_ms' => $sequentialEstimateMs,
                'saved_ms' => max(0, $sequentialEstimateMs - $elapsedMs),
                'jobs' => $jobs,
            ]);

            return redirect()
                ->route('showcase.pogo')
                ->with('success', "Pogo completed {$sku} fan-out in {$elapsedMs}ms.");
        } catch (Throwable $e) {
            return redirect()
                ->route('showcase.pogo')
                ->withInput()
                ->with('error', 'Pogo demo failed: '.$e->getMessage());
        }
    }

    /**
     * @return array<int, array{key: string, label: string, class: class-string, args: array<string, mixed>}>
     */
    private function tasks(string $sku, int $quantity): array
    {
        return [
            [
                'key' => 'price',
                'label' => 'Pricing',
                'class' => FetchPriceJob::class,
                'args' => ['sku' => $sku, 'quantity' => $quantity, 'delay_ms' => 520],
            ],
            [
                'key' => 'inventory',
                'label' => 'Inventory',
                'class' => FetchInventoryJob::class,
                'args' => ['sku' => $sku, 'quantity' => $quantity, 'delay_ms' => 380],
            ],
            [
                'key' => 'delivery',
                'label' => 'Delivery',
                'class' => FetchDeliveryWindowJob::class,
                'args' => ['sku' => $sku, 'quantity' => $quantity, 'delay_ms' => 440],
            ],
        ];
    }
}
