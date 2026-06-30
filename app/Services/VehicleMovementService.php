<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleMovementService
{
    public function decorate(Collection $vehicles): Collection
    {
        $ids = $vehicles->pluck('id')->filter()->values();
        if ($ids->isEmpty()) {
            return $vehicles;
        }

        $freshAfter = now()->subMinutes((int) env('GPS_MAX_AGE_MINUTES', 5));
        $threshold = (float) env('GPS_MOVEMENT_DISTANCE_THRESHOLD_METERS', 25);
        $latest = collect();

        foreach ($ids as $vehicleId) {
            $points = DB::table('vehicle_locations')
                ->select(['id', 'vehicle_id', 'latitude', 'longitude', 'speed', 'gps_datetime', 'created_at'])
                ->where('vehicle_id', $vehicleId)
                ->whereNotNull('gps_datetime')
                ->orderBy('gps_datetime', 'desc')
                ->orderBy('id', 'desc')
                ->limit(2)
                ->get();

            if ($points->isNotEmpty()) {
                $latest->put($vehicleId, $points);
            }
        }

        return $vehicles->map(function ($vehicle) use ($latest, $threshold, $freshAfter) {
            $points = $latest->get($vehicle->id, collect());
            $current = $points->get(0);
            $previous = $points->get(1);
            $distance = ($current && $previous) ? $this->distanceMeters($current, $previous) : 0.0;
            $isMoving = $current && $previous && $distance >= $threshold;

            $vehicle->setAttribute('is_moving', $isMoving);
            $vehicle->setAttribute('movement_distance_meters', round($distance, 1));
            $vehicle->setAttribute('movement_threshold_meters', $threshold);
            $vehicle->setAttribute('movement_basis', $current && $previous ? 'position_delta' : 'insufficient_history');
            $vehicle->setAttribute('previous_latitude', $previous?->latitude);
            $vehicle->setAttribute('previous_longitude', $previous?->longitude);
            $vehicle->setAttribute('gps_is_fresh', $vehicle->last_gps_datetime && $vehicle->last_gps_datetime->greaterThan($freshAfter));

            return $vehicle;
        });
    }

    private function distanceMeters(object $a, object $b): float
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $a->latitude);
        $lat2 = deg2rad((float) $b->latitude);
        $deltaLat = deg2rad((float) $b->latitude - (float) $a->latitude);
        $deltaLng = deg2rad((float) $b->longitude - (float) $a->longitude);

        $value = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($value), sqrt(1 - $value));
    }
}
