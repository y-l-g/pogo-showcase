<?php

declare(strict_types=1);

use App\Jobs\Pogo\FetchOpenMeteoJob;
use Pogo\JobInterface;

it('normalizes current weather data from Open-Meteo', function (): void {
    $job = new FetchOpenMeteoJob(fn () => [
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

    $result = $job->handle([
        'kind' => 'current_weather',
        'latitude' => 48.8534,
        'longitude' => 2.3488,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('current_weather')
        ->and($result['temperature'])->toBe(18.4)
        ->and($result['humidity'])->toBe(64)
        ->and(json_encode($result))->toBeString();
});

it('normalizes daily forecast data from Open-Meteo', function (): void {
    $job = new FetchOpenMeteoJob(fn () => [
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

    $result = $job->handle([
        'kind' => 'daily_forecast',
        'latitude' => 48.8534,
        'longitude' => 2.3488,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('daily_forecast')
        ->and($result['high'])->toBe(21.2)
        ->and($result['precipitation_probability'])->toBe(30)
        ->and(json_encode($result))->toBeString();
});

it('normalizes air quality data from Open-Meteo', function (): void {
    $job = new FetchOpenMeteoJob(fn () => [
        'current' => [
            'european_aqi' => 32,
            'pm10' => 14.5,
            'pm2_5' => 6.2,
            'ozone' => 78.1,
            'nitrogen_dioxide' => 18.9,
        ],
        'current_units' => [
            'pm10' => 'ug/m3',
            'pm2_5' => 'ug/m3',
            'ozone' => 'ug/m3',
            'nitrogen_dioxide' => 'ug/m3',
        ],
    ]);

    $result = $job->handle([
        'kind' => 'air_quality',
        'latitude' => 48.8534,
        'longitude' => 2.3488,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('air_quality')
        ->and($result['european_aqi'])->toBe(32)
        ->and($result['category'])->toBe('Fair')
        ->and($result['pm2_5'])->toBe(6.2)
        ->and(json_encode($result))->toBeString();
});

it('normalizes elevation data from Open-Meteo', function (): void {
    $job = new FetchOpenMeteoJob(fn () => [
        'elevation' => [35],
    ]);

    $result = $job->handle([
        'kind' => 'elevation',
        'latitude' => 48.8534,
        'longitude' => 2.3488,
    ]);

    expect($job)->toBeInstanceOf(JobInterface::class)
        ->and($result['source'])->toBe('elevation')
        ->and($result['elevation'])->toBe(35)
        ->and($result['elevation_unit'])->toBe('m')
        ->and(json_encode($result))->toBeString();
});
