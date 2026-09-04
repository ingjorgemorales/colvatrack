<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use App\Services\DriverAvailabilityService;

class ProfileController extends Controller
{
       public function edit()
    {
        $user = auth()->user()->load('role.permissions', 'assignedVehicle');

        return Inertia::render('Profile/Edit', [
            'profile' => $user,
                'availability' => [
                'can_lunch' => $user->role?->permissions
                    ->contains(fn ($permission) => $permission->module === 'perfil' && $permission->action === 'almuerzo'),
                'is_occupied' => $user->isOnLunch(),
                'driver_occupied_at' => $user->driver_occupied_at?->toISOString(),
                'has_used_today' => $user->hasUsedLunchToday(),
                'duration_minutes' => 60,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'cargo' => ['nullable', 'string', 'max:120'],
        ]);
        $request->user()->update($data);
        return back()->with('success', 'Perfil actualizado.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        return back()->with('success', 'Contrasena actualizada.');
    }

    public function occupy(DriverAvailabilityService $service)
    {
        $service->startLunch(auth()->user());
        return back()->with('success', 'Estado ocupado activado. Volveras a estar disponible en 60 minutos.');
    }
}
