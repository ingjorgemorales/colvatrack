<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DriverAvailabilityService
{
    public function startLunch(User $user): void
    {
        $user->loadMissing('role.permissions');

        $this->assertCanStart($user);

        $user->startLunch();

        $this->broadcast($user);
    }

    public function expireLunch(): int
    {
        $expired = User::whereNotNull('driver_occupied_at')
            ->where('driver_occupied_at', '<', now()->subHour())
            ->get();

        foreach ($expired as $user) {
            $user->endLunch();
            $this->broadcast($user);
        }

        return $expired->count();
    }

    private function assertCanStart(User $user): void
    {
        if ($user->hasUsedLunchToday()) {
            throw ValidationException::withMessages([
                'ocupado' => 'Ya usaste tu hora de almuerzo hoy. Podras activarla de nuevo manana despues de las 12:00 AM.',
            ]);
        }

        if (! $user->canAccess('perfil', 'almuerzo')) {
            throw ValidationException::withMessages([
                'ocupado' => 'Tu rol no tiene permitido activar el estado ocupado.',
            ]);
        }

        $vehicle = Vehicle::where('driver_id', $user->id)->first();

        if ($vehicle && $vehicle->activeToolRequest) {
            throw ValidationException::withMessages([
                'ocupado' => 'No puedes activar el estado ocupado mientras el vehiculo tiene una solicitud activa.',
            ]);
        }

        if ($user->isOnLunch()) {
            throw ValidationException::withMessages([
                'ocupado' => 'Ya estas en hora de almuerzo.',
            ]);
        }
    }

    private function broadcast(User $user): void
    {
        $vehicle = Vehicle::where('driver_id', $user->id)->first();
        if ($vehicle) {
            event(new \App\Events\DriverAvailabilityUpdated($vehicle));
        }
    }
}