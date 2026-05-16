<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Jobs\Pogo\FetchOpenMeteoJob;
use App\Services\PogoShowcase\PogoDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class RunPogoDemoController extends Controller
{
    public function __invoke(Request $request, PogoDispatcher $pogo): RedirectResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string', 'min:2', 'max:80'],
            'mode' => ['required', 'string', 'in:sequential,parallel'],
        ]);

        $mode = (string) $validated['mode'];

        if ($mode === 'parallel' && ! $pogo->available()) {
            return redirect()
                ->route('showcase.pogo')
                ->withInput()
                ->with('error', 'The Pogo extension is not loaded in this PHP runtime.');
        }

        $pool = 'external_api';

        try {
            $city = trim((string) $validated['city']);
            $location = $this->geocode($city);
            $tasks = $this->tasks($location);
            $startedAt = hrtime(true);
            $jobs = $mode === 'parallel'
                ? $this->runParallel($tasks, $pogo, $pool)
                : $this->runSequential($tasks);

            $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $results = $request->session()->get('pogo_demo_results', []);
            $results[$mode] = [
                'city' => $city,
                'mode' => $mode,
                'location' => $location,
                'pool' => $mode === 'parallel' ? $pool : null,
                'workers' => $mode === 'parallel' ? $pogo->poolSize($pool) : null,
                'elapsed_ms' => $elapsedMs,
                'jobs' => $jobs,
            ];

            $request->session()->put('pogo_demo_results', $results);

            $label = $mode === 'parallel' ? 'Pogo parallel' : 'Standard PHP';

            return redirect()
                ->route('showcase.pogo')
                ->with('success', "{$label} fetched {$location['display']} in {$elapsedMs}ms.");
        } catch (Throwable $e) {
            return redirect()
                ->route('showcase.pogo')
                ->withInput()
                ->with('error', 'Pogo demo failed: '.$e->getMessage());
        }
    }

    /**
     * @return array{name: string, admin1: string|null, country: string|null, display: string, latitude: float, longitude: float, timezone: string|null}
     */
    private function geocode(string $city): array
    {
        $response = Http::acceptJson()
            ->timeout(4)
            ->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $city,
                'count' => 1,
                'language' => 'en',
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Open-Meteo geocoding returned HTTP {$response->status()}.");
        }

        $location = $response->json('results.0');

        if (! is_array($location)) {
            throw new RuntimeException("No Open-Meteo location found for {$city}.");
        }

        $name = (string) ($location['name'] ?? $city);
        $admin1 = isset($location['admin1']) ? (string) $location['admin1'] : null;
        $country = isset($location['country']) ? (string) $location['country'] : null;
        $display = implode(', ', array_filter([$name, $admin1, $country]));

        return [
            'name' => $name,
            'admin1' => $admin1,
            'country' => $country,
            'display' => $display !== '' ? $display : $city,
            'latitude' => (float) ($location['latitude'] ?? 0),
            'longitude' => (float) ($location['longitude'] ?? 0),
            'timezone' => isset($location['timezone']) ? (string) $location['timezone'] : null,
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float}  $location
     * @return array<int, array{key: string, label: string, class: class-string, args: array<string, mixed>}>
     */
    private function tasks(array $location): array
    {
        $coordinates = [
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
        ];

        return [
            [
                'key' => 'current_weather',
                'label' => 'Current weather',
                'class' => FetchOpenMeteoJob::class,
                'args' => $coordinates + ['kind' => 'current_weather'],
            ],
            [
                'key' => 'daily_forecast',
                'label' => 'Daily forecast',
                'class' => FetchOpenMeteoJob::class,
                'args' => $coordinates + ['kind' => 'daily_forecast'],
            ],
            [
                'key' => 'air_quality',
                'label' => 'Air quality',
                'class' => FetchOpenMeteoJob::class,
                'args' => $coordinates + ['kind' => 'air_quality'],
            ],
            [
                'key' => 'elevation',
                'label' => 'Elevation',
                'class' => FetchOpenMeteoJob::class,
                'args' => $coordinates + ['kind' => 'elevation'],
            ],
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, class: class-string, args: array<string, mixed>}>  $tasks
     * @return array<int, array{key: string, label: string, result: mixed}>
     */
    private function runSequential(array $tasks): array
    {
        $job = new FetchOpenMeteoJob(function (string $endpoint, array $query, float $timeout): array {
            $payload = Http::acceptJson()
                ->timeout($timeout)
                ->get($endpoint, $query)
                ->throw()
                ->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Open-Meteo returned an invalid payload.');
            }

            return $payload;
        });

        $jobs = [];

        foreach ($tasks as $task) {
            $jobs[] = [
                'key' => $task['key'],
                'label' => $task['label'],
                'result' => $job->handle($task['args']),
            ];
        }

        return $jobs;
    }

    /**
     * @param  array<int, array{key: string, label: string, class: class-string, args: array<string, mixed>}>  $tasks
     * @return array<int, array{key: string, label: string, result: mixed}>
     */
    private function runParallel(array $tasks, PogoDispatcher $pogo, string $pool): array
    {
        $handles = [];

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
                'result' => $this->unwrap($pogo->await($handles[$task['key']], 6.0)),
            ];
        }

        return $jobs;
    }

    private function unwrap(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (! is_array($value) || ! array_key_exists('ok', $value)) {
            return $value;
        }

        if ($value['ok'] !== true) {
            throw new RuntimeException((string) ($value['error'] ?? 'Pogo job failed.'));
        }

        return $value['result'] ?? null;
    }
}
