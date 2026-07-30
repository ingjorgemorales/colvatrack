<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleMovementService
{
    public function decorate(Collection $vehicles): Collection
    {
        $ids = $vehicles->pluck('id')->filter()->values();
        $freshAfter = now()->subSeconds(max(10, (int) config('colvatrack.gps.movement_max_age_seconds', 60)));
        $minSpeed = max(0, (float) config('colvatrack.gps.movement_min_speed_kmh', 3));
        if ($ids->isEmpty()) {
            return $vehicles;
        }

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

        return $vehicles->map(function ($vehicle) use ($latest, $threshold, $freshAfter, $minSpeed) {
            $points = $latest->get($vehicle->id, collect());
            $current = $points->get(0);
            $previous = $points->get(1);
            $distance = ($current && $previous) ? $this->distanceMeters($current, $previous) : 0.0;
            $movementStatus = $this->movementStatus($vehicle, $freshAfter, $minSpeed);
            $isMoving = $movementStatus === 'moving';

            $vehicle->setAttribute('is_moving', $isMoving);
            $vehicle->setAttribute('movement_status', $movementStatus);
            $vehicle->setAttribute('movement_status_label', match ($movementStatus) {
                'moving' => 'En movimiento',
                'stopped' => 'Sin movimiento',
                default => 'GPS sin actualizar',
            });
            $vehicle->setAttribute('movement_distance_meters', round($distance, 1));
            $vehicle->setAttribute('movement_threshold_meters', $threshold);
            $vehicle->setAttribute('movement_basis', $current && $previous ? 'position_delta' : 'insufficient_history');
            $vehicle->setAttribute('previous_latitude', $previous?->latitude);
            $vehicle->setAttribute('previous_longitude', $previous?->longitude);
            $vehicle->setAttribute('gps_is_fresh', $movementStatus !== 'stale');

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

    private function movementStatus(object $vehicle, CarbonInterface $freshAfter, float $minSpeed): string
    {
        $lastGpsDate = $vehicle->last_gps_datetime;

        if (! $lastGpsDate) {
            return 'stale';
        }

        if (! $lastGpsDate instanceof CarbonInterface) {
            try {
                $lastGpsDate = CarbonImmutable::parse($lastGpsDate);
            } catch (\Throwable) {
                return 'stale';
            }
        }

        if ($lastGpsDate->lessThan($freshAfter)) {
            return 'stale';
        }

        return (float) $vehicle->current_speed > $minSpeed ? 'moving' : 'stopped';
    }
}
