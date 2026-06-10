<?php

namespace App\Services;

use App\Models\GpsProvider;
use App\Models\GpsRequestLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GpsServiceTrackService
{
    public function __construct(private VehicleLocationService $locations) {}

    public function syncLastPositions(?string $movilesOverride = null): array
    {
        $provider = GpsProvider::where('status', 'active')->first();
        if (! $provider) {
            throw new RuntimeException('No hay proveedor GPS activo.');
        }

        if ($skip = $this->guardLimits($provider)) {
            return $skip;
        }

        $moviles = $this->moviles($provider, $movilesOverride);
        if ($moviles === '') {
            return ['updated' => 0, 'created' => 0, 'message' => 'No hay moviles GPS configurados.'];
        }

        $requestedAt = now();
        $response = Http::withHeaders(['x-api-key' => $provider->api_key_encrypted ?: config('colvatrack.gps.api_key')])
            ->timeout(20)
            ->get($provider->base_url, [
                'accion' => data_get($provider->config_json, 'accion', 'lastposition'),
                'cliente' => $provider->client_code,
                'moviles' => $moviles,
            ]);

        $json = $response->json();
        $data = collect((array) data_get($json, 'data', []));

        GpsRequestLog::create([
            'gps_provider_id' => $provider->id,
            'requested_moviles' => $moviles,
            'status' => $response->successful() ? 'success' : 'error',
            'response_code' => $response->status(),
            'response_count' => $data->count(),
            'error_message' => $response->successful() ? null : $response->body(),
            'requested_at' => $requestedAt,
            'created_at' => now(),
        ]);

        $this->markRequestDone($provider);

        if (! $response->successful()) {
            $provider->update(['last_error_at' => now(), 'last_error_message' => 'Error consultando API GPS: '.$response->status()]);
            throw new RuntimeException('Error consultando API GPS: '.$response->status());
        }

        $created = 0;
        $updated = 0;
        foreach ($data as $payload) {
            if (($payload['status'] ?? null) === 'ERROR') {
                continue;
            }

            $movil = trim((string) ($payload['movil'] ?? ''));
            if ($movil === '') {
                continue;
            }

            $vehicle = Vehicle::where('external_gps_id', $movil)->orWhere('plate', $movil)->first();
            if (! $vehicle) {
                $vehicle = Vehicle::create([
                    'plate' => $movil,
                    'external_gps_id' => $movil,
                    'gps_provider_id' => $provider->id,
                    'brand' => data_get($payload, 'device_brand'),
                    'model' => data_get($payload, 'device_model'),
                    'status' => 'active',
                ]);
                $created++;
            } else {
                $vehicle->forceFill([
                    'external_gps_id' => $vehicle->external_gps_id ?: $movil,
                    'gps_provider_id' => $vehicle->gps_provider_id ?: $provider->id,
                ])->save();
            }

            $this->locations->storeFromGpsPayload($vehicle, $payload);
            $updated++;
        }

        $provider->update(['last_success_at' => now(), 'last_error_at' => null, 'last_error_message' => null]);

        return [
            'updated' => $updated,
            'created' => $created,
            'requested' => $moviles,
            'api_count' => $data->count(),
            'interval_seconds' => max(10, (int) $provider->request_interval_seconds),
        ];
    }

    private function moviles(GpsProvider $provider, ?string $override): string
    {
        $configured = $override
            ?: data_get($provider->config_json, 'moviles')
            ?: config('colvatrack.gps.moviles');

        if (is_array($configured)) {
            return collect($configured)->map(fn ($value) => trim((string) $value))->filter()->unique()->implode(',');
        }

        if (is_string($configured) && trim($configured) !== '') {
            return collect(explode(',', $configured))->map(fn ($value) => trim($value))->filter()->unique()->implode(',');
        }

        return Vehicle::where('status', 'active')
            ->where(fn ($query) => $query->whereNotNull('external_gps_id')->orWhereNotNull('plate'))
            ->get()
            ->map(fn (Vehicle $vehicle) => $vehicle->external_gps_id ?: $vehicle->plate)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(',');
    }

    private function guardLimits(GpsProvider $provider): ?array
    {
        $dailyKey = 'gps:servicetrack:daily:'.now()->toDateString();
        if (Cache::get($dailyKey, 0) >= $provider->daily_limit) {
            throw new RuntimeException('Limite diario GPS alcanzado.');
        }

        $lastKey = 'gps:servicetrack:last-request-at';
        $last = Cache::get($lastKey);
        $interval = max(10, (int) $provider->request_interval_seconds);

        if ($last) {
            $elapsed = now()->getTimestamp() - $last->getTimestamp();
            if ($elapsed < 0) {
                Cache::forget($lastKey);
                return null;
            }
            if ($elapsed < $interval) {
                return [
                    'skipped' => true,
                    'reason' => 'interval_not_elapsed',
                    'interval_seconds' => $interval,
                    'elapsed_seconds' => $elapsed,
                    'next_in_seconds' => $interval - $elapsed,
                ];
            }
        }

        return null;
    }

    private function markRequestDone(GpsProvider $provider): void
    {
        $dailyKey = 'gps:servicetrack:daily:'.now()->toDateString();
        Cache::put('gps:servicetrack:last-request-at', now(), 3600);
        Cache::increment($dailyKey);
        Cache::put($dailyKey, Cache::get($dailyKey, 1), now()->endOfDay());
    }
}

