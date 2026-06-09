<?php
namespace App\Http\Controllers;

use App\Models\GpsProvider;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(12, min((int) $request->integer('per_page', 25), 100));
        $query = Vehicle::with(['driver', 'provider'])->latest();
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn($q) => $q->where('plate', 'like', "%$search%")
                ->orWhere('brand', 'like', "%$search%")
                ->orWhere('model', 'like', "%$search%"));
        }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        return Inertia::render('Vehicles/Index', [
            'vehicles' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only('search', 'status', 'per_page'),
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
        $vehiculo->update(['status' => 'inactive']);
        return back()->with('success', 'Vehiculo desactivado.');
    }

    public function routeHistory(Request $request, Vehicle $vehiculo)
    {
        $filters = [
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $from = $this->parseDateFilter($filters['from']);
        $to = $this->parseDateFilter($filters['to']);
        $hasDateFilter = $from || $to;

        $locationsQuery = $vehiculo->locations()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($from) {
            $locationsQuery->where(function ($query) use ($from) {
                $query->where('gps_datetime', '>=', $from)
                    ->orWhere(function ($fallback) use ($from) {
                        $fallback->whereNull('gps_datetime')->where('created_at', '>=', $from);
                    });
            });
        }

        if ($to) {
            $locationsQuery->where(function ($query) use ($to) {
                $query->where('gps_datetime', '<=', $to)
                    ->orWhere(function ($fallback) use ($to) {
                        $fallback->whereNull('gps_datetime')->where('created_at', '<=', $to);
                    });
            });
        }

        if ($hasDateFilter) {
            $locations = $locationsQuery
                ->orderByRaw('COALESCE(gps_datetime, created_at) asc')
                ->orderBy('id')
                ->get();
        } else {
            $locations = $locationsQuery
                ->orderByRaw('COALESCE(gps_datetime, created_at) desc')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->reverse()
                ->values();
        }

        return Inertia::render('Vehicles/History', [
            'vehicle' => $vehiculo->load('driver', 'provider'),
            'locations' => $locations,
            'filters' => $filters,
            'hasDateFilter' => $hasDateFilter,
            'maxPoints' => $hasDateFilter ? null : 300,
            'usesFullRange' => $hasDateFilter,
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
        return [
            'vehicle' => $vehicle?->load('driver', 'provider'),
            'drivers' => User::when($driverRole, fn($q) => $q->where('role_id', $driverRole->id))->where('status', 'active')->orderBy('name')->get(),
            'providers' => GpsProvider::orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle?->id)],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.((int) date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'gps_provider_id' => ['nullable', 'exists:gps_providers,id'],
            'external_gps_id' => ['nullable', 'string', 'max:80'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'current_latitude' => ['nullable', 'numeric'],
            'current_longitude' => ['nullable', 'numeric'],
            'current_speed' => ['nullable', 'numeric'],
            'current_heading' => ['nullable', 'numeric'],
            'current_address' => ['nullable', 'string'],
            'imei' => ['nullable', 'string', 'max:80'],
            'odometer' => ['nullable', 'numeric'],
        ]);
    }
}
