<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit()
    {
        return Inertia::render('Profile/Edit', ['profile' => auth()->user()->load('role', 'assignedVehicle')]);
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
}
