<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VehicleReservationController extends Controller
{
    public function store(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $vehicle->load('activeReservation', 'activeToolRequest');

        if ($vehicle->status !== 'active') {
            throw ValidationException::withMessages(['reason' => 'Solo se pueden reservar vehiculos activos.']);
        }

        if ($vehicle->activeToolRequest) {
            throw ValidationException::withMessages(['reason' => 'Este vehiculo tiene una solicitud activa y no puede reservarse.']);
        }

        if ($vehicle->activeReservation) {
            throw ValidationException::withMessages(['reason' => 'Este vehiculo ya tiene una reserva activa.']);
        }

        $vehicle->reservations()->create([
            'reserved_by' => $request->user()->id,
            'status' => 'active',
            'reason' => $data['reason'],
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return back()->with('success', 'Vehiculo reservado.');
    }

    public function release(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'release_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation = $vehicle->activeReservation()->first();

        if (! $reservation) {
            return back()->with('error', 'Este vehiculo no tiene reserva activa.');
        }

        $reservation->update([
            'status' => 'released',
            'released_at' => now(),
            'release_comment' => $data['release_comment'] ?? null,
        ]);

        return back()->with('success', 'Reserva liberada.');
    }
}
