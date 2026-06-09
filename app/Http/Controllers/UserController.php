<?php
namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('role', 'assignedVehicle')->latest()->paginate(12),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Form', ['user' => null, 'roles' => Role::orderBy('name')->get(), 'vehicles' => Vehicle::orderBy('plate')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'], 'name' => ['required', 'string', 'max:120'], 'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:40'], 'cargo' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'], 'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['boolean'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);
        $vehicleId = $data['vehicle_id'] ?? null; unset($data['vehicle_id'], $data['password_confirmation']);
        $data['password'] = Hash::make($data['password']); $data['must_change_password'] = (bool) ($data['must_change_password'] ?? true);
        $user = User::create($data);
        if ($vehicleId) { Vehicle::where('driver_id', $user->id)->update(['driver_id' => null]); Vehicle::whereKey($vehicleId)->update(['driver_id' => $user->id]); }
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $usuario)
    {
        return Inertia::render('Users/Form', ['user' => $usuario->load('assignedVehicle'), 'roles' => Role::orderBy('name')->get(), 'vehicles' => Vehicle::orderBy('plate')->get()]);
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'], 'name' => ['required', 'string', 'max:120'], 'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users','email')->ignore($usuario->id)], 'phone' => ['nullable', 'string', 'max:40'], 'cargo' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'], 'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['boolean'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);
        $vehicleId = $data['vehicle_id'] ?? null; unset($data['vehicle_id'], $data['password_confirmation']);
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
        $data['must_change_password'] = (bool) ($data['must_change_password'] ?? false);
        $usuario->update($data);
        Vehicle::where('driver_id', $usuario->id)->update(['driver_id' => null]); if ($vehicleId) { Vehicle::whereKey($vehicleId)->update(['driver_id' => $usuario->id]); }
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario)
    {
        $usuario->update(['status' => 'inactive']);
        return back()->with('success', 'Usuario desactivado.');
    }
}
