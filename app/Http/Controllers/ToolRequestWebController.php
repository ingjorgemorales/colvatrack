<?php
namespace App\Http\Controllers;

use App\Models\ToolRequest;
use App\Models\Vehicle;
use App\Services\ToolRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ToolRequestWebController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = ToolRequest::with(['vehicle','technician','driver','items.item'])->latest();
        if ($user->hasRole('Tecnico')) { $query->where('technician_id', $user->id); }
        if ($user->hasRole('Conductor')) { $query->where('driver_id', $user->id); }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->filled('priority')) { $query->where('priority', $request->priority); }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('plate', 'like', "%$search%"))
                    ->orWhereHas('technician', fn ($tech) => $tech->where('name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"))
                    ->orWhereHas('driver', fn ($driver) => $driver->where('name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"));
            });
        }
        return Inertia::render('Requests/Index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'role' => $user->role?->name,
            'filters' => $request->only('search','status','priority'),
        ]);
    }
    public function create(Request $request)
    {
        abort_unless($request->user()->hasRole('Tecnico', 'Administrador'), 403);

        $user = $request->user();
        $userLocation = [
            'latitude' => $user->current_latitude,
            'longitude' => $user->current_longitude,
            'updated_at' => $user->location_updated_at,
        ];

        $vehicles = Vehicle::with(['driver','inventory.item.category'])
            ->where('status', 'active')
            ->get()
            ->map(function (Vehicle $vehicle) use ($userLocation) {
                $vehicle->setAttribute('distance_meters', $this->distanceFromUser($userLocation, $vehicle));
                $vehicle->setAttribute('available_items_count', $vehicle->inventory->filter(fn ($row) => (int) $row->quantity_available > 0)->count());
                $vehicle->setAttribute('has_available_inventory', $vehicle->available_items_count > 0);
                return $vehicle;
            })
            ->sortBy(fn (Vehicle $vehicle) => $vehicle->distance_meters ?? PHP_INT_MAX)
            ->values();

        return Inertia::render('Requests/Form', [
            'vehicles' => $vehicles,
            'selectedVehicleId' => $request->integer('vehicle_id') ?: null,
            'userLocation' => $userLocation,
        ]);
    }

    public function store(Request $request, ToolRequestService $service)
    {
        abort_unless($request->user()->hasRole('Tecnico', 'Administrador'), 403);
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'priority' => ['required', 'in:baja,normal,alta,critica'],
            'technician_address' => ['nullable', 'string'],
            'observation' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $data['driver_id'] = $vehicle->driver_id;
        $data['technician_id'] = $request->user()->id;
        $data['technician_latitude'] = $request->user()->current_latitude;
        $data['technician_longitude'] = $request->user()->current_longitude;
        $toolRequest = $service->create($data);
        return redirect()->route('solicitudes.show', $toolRequest)->with('success', 'Solicitud creada y herramientas reservadas.');
    }

    public function show(ToolRequest $solicitude, ToolRequestService $service)
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Administrador') || $solicitude->technician_id === $user->id || $solicitude->driver_id === $user->id, 403);
        $solicitude->load(['vehicle.inventory.item','technician','driver','items.item.category','histories.user','chat.messages.sender']);

        return Inertia::render('Requests/Show', [
            'request' => $solicitude,
            'role' => $user->role?->name,
            'allowedTransitions' => $service->allowedTransitionsFor($solicitude, $user),
        ]);
    }

    public function status(Request $request, ToolRequest $solicitude, ToolRequestService $service)
    {
        $user = $request->user();
        abort_unless($user->hasRole('Administrador') || $solicitude->driver_id === $user->id || $solicitude->technician_id === $user->id, 403);
        $data = $request->validate(['status' => ['required', 'in:aceptada,rechazada,en_camino,entregada,en_uso,para_recoger,recogida,finalizada,cancelada'], 'comment' => ['nullable', 'string']]);
        try { $service->assertCanTransition($solicitude, $user, $data['status']); $service->changeStatus($solicitude, $data['status'], $user->id, $data['comment'] ?? null); }
        catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Estado actualizado.');
    }

    private function distanceFromUser(array $userLocation, Vehicle $vehicle): ?int
    {
        if (! $userLocation['latitude'] || ! $userLocation['longitude'] || ! $vehicle->current_latitude || ! $vehicle->current_longitude) {
            return null;
        }

        $radius = 6371000;
        $lat1 = deg2rad((float) $userLocation['latitude']);
        $lat2 = deg2rad((float) $vehicle->current_latitude);
        $deltaLat = deg2rad((float) $vehicle->current_latitude - (float) $userLocation['latitude']);
        $deltaLng = deg2rad((float) $vehicle->current_longitude - (float) $userLocation['longitude']);
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return (int) round($radius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}