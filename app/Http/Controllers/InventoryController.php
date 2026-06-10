<?php
namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Vehicle;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->get('search', '');
        $perPage = min((int) $request->get('per_page', 25), 100);

        $vehicles = Vehicle::with(['driver','inventory.item.category'])
            ->when($user->hasRole('Conductor'), fn($q) => $q->where('driver_id', $user->id))
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('plate', 'like', "%{$search}%")
                  ->orWhereHas('driver', fn($q) => $q->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('plate')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'vehicles' => $vehicles,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'categories' => InventoryCategory::orderBy('name')->get(),
            'items' => InventoryItem::with('category')->where('status', 'active')->orderBy('name')->get(),
            'movements' => InventoryMovement::with(['vehicle','item'])->latest('created_at')->limit(25)->get(),
            'canManageCatalog' => $user->hasRole('Administrador'),
        ]);
    }

    public function storeItem(Request $request)
    {
        abort_unless($request->user()->hasRole('Administrador'), 403);
        $data = $request->validate([
            'category_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:40'],
        ]);
        $category = InventoryCategory::firstOrCreate(['name' => $data['category_name']], ['status' => 'active']);
        InventoryItem::firstOrCreate(['name' => $data['name']], ['inventory_category_id' => $category->id, 'description' => $data['description'] ?? null, 'unit' => $data['unit'], 'status' => 'active']);
        return back()->with('success', 'Herramienta registrada.');
    }

    public function updateItem(Request $request, InventoryItem $item)
    {
        abort_unless($request->user()->hasRole('Administrador'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_name' => ['required', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string'],
        ]);
        $category = InventoryCategory::firstOrCreate(['name' => $data['category_name']], ['status' => 'active']);
        $item->update(['name' => $data['name'], 'inventory_category_id' => $category->id, 'unit' => $data['unit'], 'description' => $data['description'] ?? null]);
        return back()->with('success', 'Herramienta actualizada.');
    }

    public function toggleItemStatus(Request $request, InventoryItem $item)
    {
        abort_unless($request->user()->hasRole('Administrador'), 403);
        $newStatus = $item->status === 'active' ? 'inactive' : 'active';
        $item->update(['status' => $newStatus]);
        return back()->with('success', "Herramienta {$item->name} " . ($newStatus === 'active' ? 'activada.' : 'desactivada.'));
    }

    public function updateStock(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_total' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ]);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        abort_unless($request->user()->hasRole('Administrador') || $vehicle->driver_id === $request->user()->id, 403);
        abort_if($data['quantity_available'] > $data['quantity_total'], 422, 'La cantidad disponible no puede superar la total.');
        $service->setStock($vehicle->id, $data['inventory_item_id'], $data['quantity_total'], $data['quantity_available'], $request->user()->id);
        return back()->with('success', 'Inventario actualizado.');
    }
}
