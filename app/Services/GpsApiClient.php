<?php

namespace App\Services;

use App\Models\GpsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GpsApiClient
{
    public function isAts(GpsProvider $provider): bool
    {
        return data_get($provider->config_json, 'protocol') === 'ats';
    }

    public function request(GpsProvider $provider, string $plates): Response
    {
        if ($this->isAts($provider)) {
            return Http::acceptJson()->timeout(20)->withoutRedirecting()->post($provider->base_url, [
                'Usuario' => $provider->client_code,
                'Password' => Crypt::decryptString($provider->api_key_encrypted),
                'Placa' => str_contains($plates, ',') ? '*' : $plates,
            ]);
        }

        return Http::withHeaders(['x-api-key' => $provider->api_key_encrypted ?: config('colvatrack.gps.api_key')])
            ->timeout(20)->get($provider->base_url, [
                'accion' => data_get($provider->config_json, 'accion', 'lastposition'),
                'cliente' => $provider->client_code, 'moviles' => $plates,
            ]);
    }

    public function positions(GpsProvider $provider, Response $response, string $plates): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('Error HTTP del proveedor GPS: '.$response->status());
        }
        $json = $response->json();
        if (! $this->isAts($provider)) {
            return (array) data_get($json, 'data', []);
        }
        if ((string) data_get($json, 'Codigo') !== '1' || ! is_array(data_get($json, 'Posiciones'))) {
            throw new RuntimeException('ATS no entrego posiciones validas. Codigo: '.(string) data_get($json, 'Codigo', 'ausente'));
        }
        $allowed = array_map(fn ($p) => strtoupper(trim($p)), explode(',', $plates));
        $result = [];
        foreach ($json['Posiciones'] as $row) {
            $plate = strtoupper(trim($row['Placa'] ?? ''));
            if (! $plate || ! in_array($plate, $allowed, true)) continue;
            if (! is_numeric($row['Latitud'] ?? null) || ! is_numeric($row['Longitud'] ?? null)
                || abs((float) $row['Latitud']) > 90 || abs((float) $row['Longitud']) > 180
                || ! is_numeric($row['Velocidad'] ?? null) || (float) $row['Velocidad'] < 0) continue;
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $row['Fecha_reporte'] ?? '', data_get($provider->config_json, 'timezone', 'America/Bogota'));
                if (! $date || $date->format('Y-m-d H:i:s') !== $row['Fecha_reporte'] || $date->isAfter(now()->addMinutes(5))) continue;
            } catch (\Throwable) { continue; }
            $result[] = [
                'movil' => $plate, 'latitud' => (float) $row['Latitud'], 'longitud' => (float) $row['Longitud'],
                'speed' => (float) $row['Velocidad'], 'course' => $row['Rumbo'] ?? null,
                'timestamp' => $date->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'location' => $row['Ubicacion'] ?? null, 'event' => $row['Evento'] ?? null,
                'imei' => $row['Imei'] ?? null, 'odometer' => is_numeric($row['Odometro'] ?? null) ? $row['Odometro'] : null,
                'status' => 'OK', 'source' => 'ats', 'original' => $row,
            ];
        }
        return $result;
    }
}
