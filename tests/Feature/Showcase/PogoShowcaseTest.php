<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\PogoShowcase\PogoDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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
                ->where('demoResults', [])
        );
});

it('runs the standard php comparison without pogo', function (): void {
    fakeOpenMeteo();

    $user = User::factory()->create();

    $response = actingAs($user)
        ->post(route('showcase.pogo.run'), [
            'city' => 'Paris',
            'mode' => 'sequential',
        ])
        ->assertRedirect(route('showcase.pogo'))
        ->assertSessionHas('success');

    $results = $response->baseResponse->getSession()->get('pogo_demo_results');
    $result = $results['sequential'];

    expect($result['city'])->toBe('Paris')
        ->and($result['mode'])->toBe('sequential')
        ->and($result['location']['display'])->toBe('Paris, Ile-de-France, France')
        ->and($result['workers'])->toBeNull()
        ->and($result['jobs'])->toHaveCount(4)
        ->and($result['jobs'][0]['result']['source'])->toBe('current_weather')
        ->and($result['jobs'][3]['result']['source'])->toBe('elevation');
});

it('redirects with an error when pogo parallel mode is unavailable', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('showcase.pogo.run'), [
            'city' => 'Paris',
            'mode' => 'parallel',
        ])
        ->assertRedirect(route('showcase.pogo'))
        ->assertSessionHas('error', 'The Pogo extension is not loaded in this PHP runtime.');
});

it('stores the pogo parallel result when pogo jobs complete', function (): void {
    app()->instance(PogoDispatcher::class, new class extends PogoDispatcher
    {
        private int $nextHandle = 1;

        /** @var array<int, array<string, mixed>> */
        private array $jobs = [];

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
            $handle = $this->nextHandle++;
            $this->jobs[$handle] = $args;

            return $handle;
        }

        public function await(int $handle, float $timeout = 5.0): mixed
        {
            $result = match ($this->jobs[$handle]['kind']) {
                'current_weather' => ['source' => 'current_weather', 'temperature' => 18.4],
                'daily_forecast' => ['source' => 'daily_forecast', 'high' => 21.2],
                'air_quality' => ['source' => 'air_quality', 'european_aqi' => 32],
                'elevation' => ['source' => 'elevation', 'elevation' => 35],
            };

            return ['ok' => true, 'result' => $result];
        }
    });

    fakeOpenMeteo();

    $user = User::factory()->create();

    $response = actingAs($user)
        ->post(route('showcase.pogo.run'), [
            'city' => 'Paris',
            'mode' => 'parallel',
        ])
        ->assertRedirect(route('showcase.pogo'))
        ->assertSessionHas('success');

    $results = $response->baseResponse->getSession()->get('pogo_demo_results');
    $result = $results['parallel'];

    expect($result['city'])->toBe('Paris')
        ->and($result['mode'])->toBe('parallel')
        ->and($result['location']['display'])->toBe('Paris, Ile-de-France, France')
        ->and($result['location']['latitude'])->toBe(48.8534)
        ->and($result['pool'])->toBe('external_api')
        ->and($result['workers'])->toBe(8)
        ->and($result['jobs'])->toHaveCount(4)
        ->and($result['jobs'][0]['result']['source'])->toBe('current_weather')
        ->and($result['jobs'][3]['result']['source'])->toBe('elevation');
});

it('keeps standard and pogo results together for comparison', function (): void {
    fakeOpenMeteo();

    $user = User::factory()->create();

    $response = actingAs($user)
        ->withSession([
            'pogo_demo_results' => [
                'parallel' => [
                    'city' => 'Paris',
                    'mode' => 'parallel',
                    'location' => ['display' => 'Paris, France'],
                    'elapsed_ms' => 120,
                    'jobs' => [],
                ],
            ],
        ])
        ->post(route('showcase.pogo.run'), [
            'city' => 'Paris',
            'mode' => 'sequential',
        ])
        ->assertRedirect(route('showcase.pogo'));

    $results = $response->baseResponse->getSession()->get('pogo_demo_results');

    expect($results)->toHaveKeys(['parallel', 'sequential'])
        ->and($results['parallel']['mode'])->toBe('parallel')
        ->and($results['sequential']['mode'])->toBe('sequential');
});

function fakeOpenMeteo(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, 'geocoding-api.open-meteo.com')) {
            return Http::response([
                'results' => [[
                    'name' => 'Paris',
                    'admin1' => 'Ile-de-France',
                    'country' => 'France',
                    'latitude' => 48.8534,
                    'longitude' => 2.3488,
                    'timezone' => 'Europe/Paris',
                ]],
            ]);
        }

        if (str_contains($url, 'air-quality-api.open-meteo.com')) {
            return Http::response([
                'current' => [
                    'european_aqi' => 32,
                    'pm10' => 14.5,
                    'pm2_5' => 6.2,
                ],
                'current_units' => [
                    'pm10' => 'ug/m3',
                    'pm2_5' => 'ug/m3',
                ],
            ]);
        }

        if (str_contains($url, 'api.open-meteo.com/v1/elevation')) {
            return Http::response([
                'elevation' => [35],
            ]);
        }

        if (isset($query['daily'])) {
            return Http::response([
                'daily' => [
                    'temperature_2m_max' => [21.2],
                    'temperature_2m_min' => [11.8],
                    'precipitation_probability_max' => [30],
                ],
                'daily_units' => [
                    'temperature_2m_max' => 'C',
                    'precipitation_probability_max' => '%',
                ],
            ]);
        }

        return Http::response([
            'current' => [
                'temperature_2m' => 18.4,
                'relative_humidity_2m' => 64,
                'wind_speed_10m' => 12.1,
                'weather_code' => 3,
            ],
            'current_units' => [
                'temperature_2m' => 'C',
                'relative_humidity_2m' => '%',
                'wind_speed_10m' => 'km/h',
            ],
        ]);
    });
}
