<?php
namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $perPage = min((int) $request->integer('per_page', 10), 100);
        $users = User::with('role', 'assignedVehicle');
        if (! $actor->hasRole('Superadministrador')) {
            $users->whereDoesntHave('role', fn ($q) => $q->where('name', 'Superadministrador'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $users->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('cedula', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $users->where('status', $request->string('status'));
        }
        return Inertia::render('Users/Index', [
            'users' => $users->latest()->paginate($perPage)->withQueryString(),
            'roles' => $this->availableRoles($actor),
            'filters' => $request->only('search', 'status', 'per_page'),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Users/Form', ['user' => null, 'roles' => $this->availableRoles($request->user()), 'vehicles' => $this->availableVehicles()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_id' => ['required', $this->roleRule($request->user())], 'name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'integer', 'min:1', 'unique:users,cedula'],
            'email' => ['required', 'email', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:40'], 'cargo' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
            'must_change_password' => ['boolean'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);
        $data['email'] = strtolower(trim($data['email']));
        $vehicleId = $this->validatedVehicleAssignment($data);
        unset($data['vehicle_id']);
        $plainPassword = Str::password(12);
        $data['password'] = Hash::make($plainPassword); $data['must_change_password'] = true;
        $user = User::create($data);
        $this->syncDriverVehicle($user, $vehicleId);
        try { Mail::to($user->email)->send(new WelcomeMail($user->name, $user->email, $plainPassword)); } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('No fue posible enviar correo de bienvenida', ['to' => $user->email, 'error' => $e->getMessage()]); }
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $usuario)
    {
        $this->authorizeManageUser(auth()->user(), $usuario);

        return Inertia::render('Users/Form', ['user' => $usuario->load('assignedVehicle'), 'roles' => $this->availableRoles(auth()->user()), 'vehicles' => $this->availableVehicles($usuario)]);
    }

    public function update(Request $request, User $usuario)
    {
        $this->authorizeManageUser($request->user(), $usuario);

        $data = $request->validate([
            'role_id' => ['required', $this->roleRule($request->user())], 'name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'integer', 'min:1', Rule::unique('users','cedula')->ignore($usuario->id)],
            'email' => ['required', 'email', Rule::unique('users','email')->ignore($usuario->id)], 'phone' => ['nullable', 'string', 'max:40'], 'cargo' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'], 'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['boolean'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);
        $vehicleId = $this->validatedVehicleAssignment($data, $usuario);
        unset($data['vehicle_id'], $data['password_confirmation']);
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
        $data['must_change_password'] = (bool) ($data['must_change_password'] ?? false);
        $usuario->update($data);
        $this->syncDriverVehicle($usuario, $vehicleId);
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario)
    {
        $this->authorizeManageUser(auth()->user(), $usuario);
        $usuario->update(['status' => 'inactive']);
        $this->releaseDriverVehicle($usuario);
        return back()->with('success', 'Usuario desactivado.');
    }

    private function availableVehicles(?User $user = null)
    {
        return Vehicle::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->whereNull('driver_id')
                ->when($user, fn ($query) => $query->orWhere('driver_id', $user->id)))
            ->orderBy('plate')
            ->get();
    }

    private function validatedVehicleAssignment(array $data, ?User $user = null): ?int
    {
        $vehicleId = $data['vehicle_id'] ?? null;
        $conductorRoleId = Role::where('name', 'Conductor')->value('id');

        if (! $vehicleId || (int) $data['role_id'] !== (int) $conductorRoleId || $data['status'] !== 'active') {
            return null;
        }

        $vehicle = Vehicle::whereKey($vehicleId)->where('status', 'active')->first();
        if (! $vehicle) {
            throw ValidationException::withMessages(['vehicle_id' => 'El vehiculo seleccionado no esta activo o no existe.']);
        }

        if ($vehicle->driver_id && (! $user || (int) $vehicle->driver_id !== (int) $user->id)) {
            throw ValidationException::withMessages(['vehicle_id' => 'El vehiculo '.$vehicle->plate.' ya tiene otro conductor asignado.']);
        }

        return (int) $vehicleId;
    }

    private function syncDriverVehicle(User $user, ?int $vehicleId): void
    {
        $this->releaseDriverVehicle($user);

        if ($vehicleId) {
            Vehicle::whereKey($vehicleId)->update(['driver_id' => $user->id]);
        }
    }

    private function releaseDriverVehicle(User $user): void
    {
        Vehicle::where('driver_id', $user->id)->update(['driver_id' => null]);
    }

    private function availableRoles(User $actor)
    {
        return Role::query()
            ->when(! $actor->hasRole('Superadministrador'), fn ($q) => $q->where('name', '!=', 'Superadministrador'))
            ->orderBy('name')
            ->get();
    }

    private function roleRule(User $actor)
    {
        $rule = Rule::exists('roles', 'id');

        if (! $actor->hasRole('Superadministrador')) {
            $rule->where(fn ($q) => $q->where('name', '!=', 'Superadministrador'));
        }

        return $rule;
    }

    private function authorizeManageUser(User $actor, User $target): void
    {
        abort_if(! $actor->hasRole('Superadministrador') && $target->role?->name === 'Superadministrador', 403);
    }
}
