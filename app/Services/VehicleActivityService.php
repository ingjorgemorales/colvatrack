<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class VehicleActivityService
{
    public function rangeFromRequest(Request $request, string $fromKey = 'from', string $toKey = 'to'): array
    {
        $from = $this->parseDate($request->string($fromKey)->toString()) ?? now()->startOfDay()->toImmutable();
        $to = $this->parseDate($request->string($toKey)->toString(), true) ?? now()->endOfDay()->toImmutable();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $threshold = (float) env('GPS_MOVEMENT_DISTANCE_THRESHOLD_METERS', 25);
        $freshAfter = now()->subMinutes((int) env('GPS_MAX_AGE_MINUTES', 5));
        $vehicles = Vehicle::with('driver')
            ->where('status', 'active')
            ->orderBy('plate')
            ->get();

        $locations = VehicleLocation::whereIn('vehicle_id', $vehicles->pluck('id'))
            ->whereNotNull('gps_datetime')
            ->where('gps_datetime', '>=', $from)
            ->where('gps_datetime', '<=', $to)
            ->orderBy('vehicle_id')
            ->orderBy('gps_datetime')
            ->orderBy('id')
            ->get()
            ->groupBy('vehicle_id');

        $rows = $vehicles->map(function (Vehicle $vehicle) use ($locations, $threshold, $freshAfter, $from, $to) {
            $points = $locations->get($vehicle->id, collect())->values();
            $distance = 0.0;
            $hasMovementByDistance = false;
            $previous = null;

            foreach ($points as $point) {
                if ($previous) {
                    $segmentDistance = $this->distanceMeters($previous, $point);
                    $distance += $segmentDistance;
                    if ($segmentDistance >= $threshold) {
                        $hasMovementByDistance = true;
                    }
                }

                $previous = $point;
            }

            $maxSpeed = (float) ($points->max('speed') ?? 0);
            $hasMovement = $maxSpeed > 0 || $hasMovementByDistance;
            $firstPoint = $points->first();
            $lastPoint = $points->last();
            $fromParam = $from->format('Y-m-d\TH:i');
            $toParam = $to->format('Y-m-d\TH:i');

            return [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'driver' => $vehicle->driver ? trim($vehicle->driver->name.' '.$vehicle->driver->last_name) : 'Sin conductor',
                'status' => $hasMovement ? 'moving' : 'stopped',
                'status_label' => $hasMovement ? 'En movimiento' : 'Sin movimiento',
                'points_count' => $points->count(),
                'max_speed' => round($maxSpeed, 1),
                'distance_meters' => round($distance, 1),
                'distance_km' => round($distance / 1000, 2),
                'first_gps_datetime' => $this->formatDateTime($firstPoint?->gps_datetime),
                'last_gps_datetime' => $this->formatDateTime($lastPoint?->gps_datetime),
                'vehicle_last_gps_datetime' => $this->formatDateTime($vehicle->last_gps_datetime),
                'gps_is_fresh' => $vehicle->last_gps_datetime && $vehicle->last_gps_datetime->greaterThan($freshAfter),
                'movement_basis' => $maxSpeed > 0 ? 'speed' : ($hasMovementByDistance ? 'distance' : 'none'),
                'route_url' => '/vehiculos/'.$vehicle->id.'/recorrido?'.http_build_query([
                    'from' => $fromParam,
                    'to' => $toParam,
                ]),
            ];
        })->values();

        $moving = $rows->where('status', 'moving');
        $stopped = $rows->where('status', 'stopped');

        return [
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'from_datetime' => $from->format('Y-m-d H:i:s'),
                'to_datetime' => $to->format('Y-m-d H:i:s'),
            ],
            'summary' => [
                'total_count' => $rows->count(),
                'moving_count' => $moving->count(),
                'stopped_count' => $stopped->count(),
                'gps_fresh_count' => $rows->where('gps_is_fresh', true)->count(),
                'gps_stale_count' => $rows->where('gps_is_fresh', false)->count(),
                'points_count' => $rows->sum('points_count'),
                'distance_km' => round($rows->sum('distance_meters') / 1000, 2),
                'threshold_meters' => $threshold,
            ],
            'rows' => $rows,
            'detail_url' => '/vehiculos/actividad?'.http_build_query([
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]),
        ];
    }

    private function parseDate(?string $value, bool $endOfDay = false): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, config('app.timezone'));

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDateTime($value): ?string
    {
        if (! $value) {
            return null;
        }

        return CarbonImmutable::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
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
