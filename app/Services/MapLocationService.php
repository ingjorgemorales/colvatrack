<?php

namespace App\Services;

use App\Models\ToolRequest;
use App\Models\User;
use App\Models\Vehicle;

class MapLocationService
{
    public function __construct(private VehicleMovementService $movement) {}

    public function data(): array
    {
        $user = auth()->user();

        $vehicles = Vehicle::with(['driver', 'inventory.item'])
            ->where('status', 'active')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->when($user?->hasRole('Conductor'), fn ($q) => $q->where('driver_id', $user->id))
            ->get();

        return [
            'vehicles' => $this->movement->decorate($vehicles)->values(),
            'technicians' => $this->technicians($user),
        ];
    }

    private function technicians(?User $viewer)
    {
        $freshAfter = now()->subMinutes(config('colvatrack.location.max_age_minutes', 10));

        $query = User::with('role')
            ->where('status', 'active')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereHas('role', fn ($q) => $q->where('name', 'Tecnico'));

    if ($viewer?->hasRole('Conductor')) {
        $vehicleIds = Vehicle::where('driver_id', $viewer->id)->pluck('id');
        $techIds = ToolRequest::whereIn('vehicle_id', $vehicleIds)->pluck('technician_id')->unique();
        $query->whereIn('id', $techIds);
    }

    if ($viewer?->hasRole('Tecnico')) {
        $query->whereKey($viewer->id);
    }

        return $query->orderBy('name')
            ->get(['id', 'role_id', 'name', 'last_name', 'email', 'phone', 'current_latitude', 'current_longitude', 'location_updated_at'])
            ->map(function (User $user) use ($freshAfter) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role?->name,
                    'current_latitude' => $user->current_latitude,
                    'current_longitude' => $user->current_longitude,
                    'location_updated_at' => $user->location_updated_at,
                    'location_is_fresh' => $user->location_updated_at?->greaterThan($freshAfter) ?? false,
                ];
            })
            ->values();
    }
}