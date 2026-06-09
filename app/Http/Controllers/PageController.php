<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GpsProvider;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ToolRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MapLocationService;
use Inertia\Inertia;

class PageController extends Controller
{
    public function map(MapLocationService $mapLocations)
    {
        return Inertia::render('Map/Index', $mapLocations->data());
    }

    public function module(string $module)
    {
        $data = match ($module) {
            'solicitudes' => ToolRequest::with(['vehicle', 'technician', 'driver'])->latest()->paginate(20),
            'notificaciones' => Notification::where('user_id', auth()->id())->latest()->paginate(20),
            'inventario' => InventoryItem::with('category')->paginate(20),
            'vehiculos' => Vehicle::with('driver')->paginate(20),
            'usuarios' => User::with('role', 'assignedVehicle')->paginate(20),
            'roles' => Role::with('permissions')->paginate(20),
            'auditoria' => AuditLog::with('user')->latest()->paginate(20),
            'configuracion-gps' => GpsProvider::paginate(20),
            default => null,
        };
        abort_if($data === null, 404);

        return Inertia::render('Modules/Index', ['module' => $module, 'records' => $data]);
    }

    public function profile()
    {
        return Inertia::render('Modules/Index', ['module' => 'perfil', 'records' => ['data' => [auth()->user()->load('role', 'assignedVehicle')]]]);
    }
}
