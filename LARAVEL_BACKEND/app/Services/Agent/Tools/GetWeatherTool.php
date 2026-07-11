<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GetWeatherTool implements AgentTool
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get current weather for a city (delivery planning, event promotions). Uses Open-Meteo free API.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name e.g. Nairobi'],
                'country' => ['type' => 'string', 'description' => 'Country code or name e.g. KE'],
            ],
            'required' => ['city'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        if (! config('agent.world.weather_enabled', true)) {
            return ['enabled' => false, 'message' => 'Weather tool disabled.'];
        }

        $city = trim((string) ($arguments['city'] ?? ''));
        if ($city === '') {
            return ['error' => 'City is required.'];
        }

        $country = trim((string) ($arguments['country'] ?? ''));

        try {
            $geo = Http::timeout(8)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $city,
                'count' => 1,
                'language' => 'en',
                'format' => 'json',
            ]);

            if (! $geo->successful()) {
                return ['error' => 'Geocoding unavailable.'];
            }

            $results = $geo->json('results') ?? [];
            if ($results === []) {
                return ['error' => "Could not find city: {$city}"];
            }

            $place = $results[0];
            $lat = $place['latitude'] ?? null;
            $lon = $place['longitude'] ?? null;
            if ($lat === null || $lon === null) {
                return ['error' => 'Invalid geocode result.'];
            }

            $weather = Http::timeout(8)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
            ]);

            if (! $weather->successful()) {
                return ['error' => 'Weather API unavailable.'];
            }

            $current = $weather->json('current') ?? [];

            return [
                'city' => $place['name'] ?? $city,
                'country' => $place['country'] ?? $country,
                'temperature_c' => $current['temperature_2m'] ?? null,
                'humidity_pct' => $current['relative_humidity_2m'] ?? null,
                'wind_kmh' => $current['wind_speed_10m'] ?? null,
                'weather_code' => $current['weather_code'] ?? null,
                'summary' => $this->codeToSummary((int) ($current['weather_code'] ?? 0)),
            ];
        } catch (\Throwable $e) {
            Log::warning('Weather tool failed', ['error' => $e->getMessage()]);

            return ['error' => 'Weather lookup failed.'];
        }
    }

    private function codeToSummary(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            $code <= 3 => 'Partly cloudy',
            $code <= 48 => 'Foggy',
            $code <= 67 => 'Rain',
            $code <= 77 => 'Snow',
            $code <= 82 => 'Rain showers',
            default => 'Stormy',
        };
    }
}
