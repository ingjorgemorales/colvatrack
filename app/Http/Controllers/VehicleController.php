<?php
namespace App\Http\Controllers;

use App\Models\GpsProvider;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 10), 100);
        $query = Vehicle::with(['driver', 'provider', 'project', 'activeReservation.reservedBy'])->latest();
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn($q) => $q->where('plate', 'like', "%$search%")
                ->orWhere('brand', 'like', "%$search%")
                ->orWhere('model', 'like', "%$search%"));
        }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->input('movement') === 'moving') {
            $query->where('current_speed', '>', 0);
        }
        if ($request->input('movement') === 'stopped') {
            $query->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('current_speed')->orWhere('current_speed', '<=', 0));
        }
        if (in_array($request->input('reserved'), ['active', 'none'], true)) {
            $reservedVehicleIds = VehicleReservation::query()
                ->select('vehicle_id')
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));

            $request->input('reserved') === 'active'
                ? $query->whereIn('id', $reservedVehicleIds)
                : $query->whereNotIn('id', $reservedVehicleIds);
        }
        return Inertia::render('Vehicles/Index', [
            'vehicles' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only('search', 'status', 'movement', 'reserved', 'per_page'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Vehicles/Form', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Vehicle::create($data);
        return redirect()->route('vehiculos.index')->with('success', 'Vehiculo creado.');
    }

    public function edit(Vehicle $vehiculo)
    {
        return Inertia::render('Vehicles/Form', $this->formData($vehiculo));
    }

    public function update(Request $request, Vehicle $vehiculo)
    {
        $vehiculo->update($this->validated($request, $vehiculo));
        return redirect()->route('vehiculos.index')->with('success', 'Vehiculo actualizado.');
    }

    public function destroy(Vehicle $vehiculo)
    {
        $vehiculo->update(['status' => 'inactive', 'driver_id' => null]);
        return back()->with('success', 'Vehiculo desactivado.');
    }

    public function routeHistory(Request $request, Vehicle $vehiculo)
    {
        $from = $this->parseDateFilter($request->string('from')->toString()) ?? now()->subHours(24);
        $to = $this->parseDateFilter($request->string('to')->toString()) ?? now();

        $locations = $vehiculo->locations()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('gps_datetime', '>=', $from)
            ->where('gps_datetime', '<=', $to)
            ->orderBy('gps_datetime', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Vehicles/History', [
            'vehicle' => $vehiculo->load('driver', 'provider'),
            'locations' => $locations,
            'filters' => [
                'from' => $from->format('Y-m-d\TH:i'),
                'to' => $to->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    private function parseDateFilter(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'))->setSecond(0);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formData(?Vehicle $vehicle = null): array
    {
        $driverRole = Role::where('name', 'Conductor')->first();
        $assignedDriverIds = Vehicle::query()
            ->when($vehicle, fn ($q) => $q->whereKeyNot($vehicle->id))
            ->whereNotNull('driver_id')
            ->pluck('driver_id');

        return [
            'vehicle' => $vehicle?->load('driver', 'provider', 'project'),
            'drivers' => User::when($driverRole, fn($q) => $q->where('role_id', $driverRole->id))
                ->where('status', 'active')
                ->whereNotIn('id', $assignedDriverIds)
                ->orderBy('name')
                ->get(),
            'providers' => GpsProvider::orderBy('name')->get(),
            'projects' => Project::where('status', 'active')
                ->when($vehicle?->project_id, fn ($q) => $q->orWhere('id', $vehicle->project_id))
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $driverRoleId = Role::where('name', 'Conductor')->value('id');
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle?->id)],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.((int) date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'gps_provider_id' => ['nullable', 'exists:gps_providers,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'external_gps_id' => ['nullable', 'string', 'max:80'],
            'driver_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->when($driverRoleId, fn ($query) => $query->where('role_id', $driverRoleId))
                    ->where('status', 'active')),
            ],
            'current_latitude' => ['nullable', 'numeric'],
            'current_longitude' => ['nullable', 'numeric'],
            'current_speed' => ['nullable', 'numeric'],
            'current_heading' => ['nullable', 'numeric'],
            'current_address' => ['nullable', 'string'],
            'imei' => ['nullable', 'string', 'max:80'],
            'odometer' => ['nullable', 'numeric'],
        ]);

        if (! empty($data['driver_id'])) {
            $assignedVehicle = Vehicle::where('driver_id', $data['driver_id'])
                ->when($vehicle, fn ($q) => $q->whereKeyNot($vehicle->id))
                ->first();

            if ($assignedVehicle) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Este conductor ya esta asignado al vehiculo '.$assignedVehicle->plate.'.',
                ]);
            }
        }

        if ($data['status'] === 'inactive') {
            $data['driver_id'] = null;
        }

        return $data;
    }
}
