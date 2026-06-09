<?php

namespace App\Services;

use App\Events\VehicleLocationUpdated;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;

class VehicleLocationService
{
    public function storeFromGpsPayload(Vehicle $vehicle, array $payload): Vehicle
    {
        $vehicle->update([
            'current_latitude' => $payload['latitud'] ?? null,
            'current_longitude' => $payload['longitud'] ?? null,
            'current_speed' => $payload['speed'] ?? null,
            'current_heading' => $payload['course'] ?? null,
            'last_gps_event' => $payload['event'] ?? null,
            'last_gps_datetime' => $payload['timestamp'] ?? null,
            'current_address' => $payload['location'] ?? null,
            'imei' => $payload['imei'] ?? null,
            'odometer' => $payload['odometer'] ?? null,
            'gps_status' => $payload['status'] ?? null,
            'gps_device_brand' => $payload['device_brand'] ?? null,
            'gps_device_model' => $payload['device_model'] ?? null,
            'battery' => $payload['battery'] ?? null,
            'gps_marker_url' => data_get($payload, 'images.marker'),
        ]);

        if ($vehicle->current_latitude && $vehicle->current_longitude) {
            $vehicle->locations()->create([
                'latitude' => $vehicle->current_latitude,
                'longitude' => $vehicle->current_longitude,
                'speed' => $vehicle->current_speed,
                'heading' => $vehicle->current_heading,
                'address' => $vehicle->current_address,
                'gps_event' => $vehicle->last_gps_event,
                'gps_datetime' => $vehicle->last_gps_datetime,
                'odometer' => $vehicle->odometer,
                'raw_payload_json' => $payload,
                'created_at' => now(),
            ]);

            if (filter_var(env('GPS_BROADCAST_LOCATIONS', false), FILTER_VALIDATE_BOOLEAN)) {
                try {
                    broadcast(new VehicleLocationUpdated($vehicle->fresh('driver')))->toOthers();
                } catch (\Throwable $e) {
                    Log::warning('No fue posible emitir ubicacion GPS por WebSocket', [
                        'vehicle_id' => $vehicle->id,
                        'plate' => $vehicle->plate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $vehicle;
    }
}
