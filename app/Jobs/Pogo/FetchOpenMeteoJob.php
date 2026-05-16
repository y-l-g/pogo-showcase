<?php

declare(strict_types=1);

namespace App\Jobs\Pogo;

use Closure;
use InvalidArgumentException;
use Pogo\JobInterface;
use RuntimeException;

final class FetchOpenMeteoJob implements JobInterface
{
    private const ENDPOINTS = [
        'current_weather' => 'https://api.open-meteo.com/v1/forecast',
        'daily_forecast' => 'https://api.open-meteo.com/v1/forecast',
        'air_quality' => 'https://air-quality-api.open-meteo.com/v1/air-quality',
        'elevation' => 'https://api.open-meteo.com/v1/elevation',
    ];

    public function __construct(private readonly ?Closure $fetcher = null) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $kind = (string) ($args['kind'] ?? 'current_weather');

        if (! array_key_exists($kind, self::ENDPOINTS)) {
            throw new InvalidArgumentException("Unsupported Open-Meteo job [{$kind}].");
        }

        $latitude = (float) ($args['latitude'] ?? 0);
        $longitude = (float) ($args['longitude'] ?? 0);
        $timeout = max(1.0, (float) ($args['timeout'] ?? 5.0));
        $query = $this->query($kind, $latitude, $longitude);
        $payload = $this->fetcher
            ? ($this->fetcher)(self::ENDPOINTS[$kind], $query, $timeout)
            : $this->fetchJson(self::ENDPOINTS[$kind], $query, $timeout);

        if (! is_array($payload)) {
            throw new RuntimeException('Open-Meteo returned an invalid payload.');
        }

        return match ($kind) {
            'current_weather' => $this->currentWeather($payload),
            'daily_forecast' => $this->dailyForecast($payload),
            'air_quality' => $this->airQuality($payload),
            'elevation' => $this->elevation($payload),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $kind, float $latitude, float $longitude): array
    {
        $base = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if ($kind === 'current_weather') {
            return $base + [
                'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
                'timezone' => 'auto',
                'forecast_days' => 1,
            ];
        }

        if ($kind === 'daily_forecast') {
            return $base + [
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                'timezone' => 'auto',
                'forecast_days' => 1,
            ];
        }

        if ($kind === 'elevation') {
            return $base;
        }

        return $base + [
            'current' => 'european_aqi,pm10,pm2_5,ozone,nitrogen_dioxide',
            'timezone' => 'auto',
            'forecast_days' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function fetchJson(string $endpoint, array $query, float $timeout): array
    {
        $url = $endpoint.'?'.http_build_query($query);
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: application/json\r\nUser-Agent: pogo-showcase\r\n",
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuntimeException('Open-Meteo request failed.');
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1 && (int) $matches[1] >= 400) {
            throw new RuntimeException("Open-Meteo returned HTTP {$matches[1]}.");
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Open-Meteo returned non-object JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function currentWeather(array $payload): array
    {
        $current = $this->array($payload['current'] ?? []);
        $currentUnits = $this->array($payload['current_units'] ?? []);

        return [
            'source' => 'current_weather',
            'temperature' => $current['temperature_2m'] ?? null,
            'temperature_unit' => $currentUnits['temperature_2m'] ?? null,
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'humidity_unit' => $currentUnits['relative_humidity_2m'] ?? null,
            'wind_speed' => $current['wind_speed_10m'] ?? null,
            'wind_speed_unit' => $currentUnits['wind_speed_10m'] ?? null,
            'weather_code' => $current['weather_code'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dailyForecast(array $payload): array
    {
        $daily = $this->array($payload['daily'] ?? []);
        $dailyUnits = $this->array($payload['daily_units'] ?? []);

        return [
            'source' => 'daily_forecast',
            'high' => $this->first($daily['temperature_2m_max'] ?? null),
            'low' => $this->first($daily['temperature_2m_min'] ?? null),
            'daily_temperature_unit' => $dailyUnits['temperature_2m_max'] ?? null,
            'precipitation_probability' => $this->first($daily['precipitation_probability_max'] ?? null),
            'precipitation_probability_unit' => $dailyUnits['precipitation_probability_max'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function airQuality(array $payload): array
    {
        $current = $this->array($payload['current'] ?? []);
        $units = $this->array($payload['current_units'] ?? []);
        $aqi = $current['european_aqi'] ?? null;

        return [
            'source' => 'air_quality',
            'european_aqi' => $aqi,
            'category' => is_numeric($aqi) ? $this->aqiCategory((int) $aqi) : null,
            'pm10' => $current['pm10'] ?? null,
            'pm10_unit' => $units['pm10'] ?? null,
            'pm2_5' => $current['pm2_5'] ?? null,
            'pm2_5_unit' => $units['pm2_5'] ?? null,
            'ozone' => $current['ozone'] ?? null,
            'ozone_unit' => $units['ozone'] ?? null,
            'nitrogen_dioxide' => $current['nitrogen_dioxide'] ?? null,
            'nitrogen_dioxide_unit' => $units['nitrogen_dioxide'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function elevation(array $payload): array
    {
        return [
            'source' => 'elevation',
            'elevation' => $this->first($payload['elevation'] ?? null),
            'elevation_unit' => 'm',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function first(mixed $value): mixed
    {
        return is_array($value) ? ($value[0] ?? null) : null;
    }

    private function aqiCategory(int $aqi): string
    {
        return match (true) {
            $aqi <= 20 => 'Good',
            $aqi <= 40 => 'Fair',
            $aqi <= 60 => 'Moderate',
            $aqi <= 80 => 'Poor',
            $aqi <= 100 => 'Very poor',
            default => 'Extremely poor',
        };
    }
}
