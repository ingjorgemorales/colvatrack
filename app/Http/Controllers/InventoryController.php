<?php
namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Vehicle;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->get('search', '');
        $toolId = $request->get('tool_id', '');
        $perPage = min((int) $request->get('per_page', 10), 100);

        $activeInventory = fn ($q) => $q
            ->whereHas('item', fn ($item) => $item->where('status', 'active'))
            ->with('item.category');

        $vehicles = Vehicle::with(['driver', 'inventory' => $activeInventory])
            ->where('status', 'active')
            ->when($user->hasRole('Conductor'), fn($q) => $q->where('driver_id', $user->id))
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('plate', 'like', "%{$search}%")
                  ->orWhereHas('driver', fn($q) => $q->where('name', 'like', "%{$search}%"));
            }))
            ->when($toolId, fn($q) => $q->whereHas('inventory', fn($q) => $q
                ->where('inventory_item_id', $toolId)
                ->whereHas('item', fn ($item) => $item->where('status', 'active'))))
            ->orderBy('plate')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'vehicles' => $vehicles,
            'filters' => ['search' => $search, 'tool_id' => $toolId, 'per_page' => $perPage],
            'items' => InventoryItem::with('category')->withSum('vehicleInventories', 'quantity_total')->where('status', 'active')->orderBy('name')->get(),
            'canManageCatalog' => $this->canManageInventory($user, 'gestionar'),
        ]);
    }

    public function movements(Request $request)
    {
        $userSearch = trim((string) $request->get('user', ''));
        $dateFrom = (string) $request->get('date_from', '');
        $dateTo = (string) $request->get('date_to', '');
        $vehicleId = (string) $request->get('vehicle_id', '');
        $categoryId = (string) $request->get('category_id', '');
        $itemId = (string) $request->get('item_id', '');
        $movementType = (string) $request->get('movement_type', '');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $movements = InventoryMovement::with(['vehicle','item.category','creator'])
            ->when($userSearch, fn ($q) => $q->whereHas('creator', fn ($creator) => $creator
                ->where('name', 'like', "%{$userSearch}%")
                ->orWhere('last_name', 'like', "%{$userSearch}%")
                ->orWhere('email', 'like', "%{$userSearch}%")))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when($categoryId, fn ($q) => $q->whereHas('item', fn ($item) => $item->where('inventory_category_id', $categoryId)))
            ->when($itemId, fn ($q) => $q->where('inventory_item_id', $itemId))
            ->when($movementType, fn ($q) => $q->where('movement_type', $movementType))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
            'vehicles' => Vehicle::where('status', 'active')->orderBy('plate')->get(['id', 'plate']),
            'categories' => InventoryCategory::orderBy('name')->get(['id', 'name']),
            'items' => InventoryItem::with('category')->where('status', 'active')->orderBy('name')->get(['id', 'inventory_category_id', 'name', 'unit']),
            'movementTypes' => InventoryMovement::select('movement_type')->distinct()->orderBy('movement_type')->pluck('movement_type'),
            'filters' => [
                'user' => $userSearch,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'vehicle_id' => $vehicleId,
                'category_id' => $categoryId,
                'item_id' => $itemId,
                'movement_type' => $movementType,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function catalog(Request $request)
    {
        abort_unless($this->canManageInventory($request->user(), 'gestionar'), 403);

        $search = $request->get('search', '');
        $categoryId = $request->get('category_id', '');
        $status = $request->get('status', '');
        $perPage = min((int) $request->get('per_page', 15), 100);

        return Inertia::render('Inventory/Catalog', [
            'items' => InventoryItem::with('category')
                ->withSum('vehicleInventories', 'quantity_total')
                ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                }))
                ->when($categoryId, fn ($q) => $q->where('inventory_category_id', $categoryId))
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString(),
            'categories' => InventoryCategory::orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function storeItem(Request $request)
    {
        abort_unless($this->canManageInventory($request->user(), 'crear'), 403);
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
        abort_unless($this->canManageInventory($request->user(), 'editar'), 403);
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
        abort_unless($this->canManageInventory($request->user(), 'editar'), 403);
        $newStatus = $item->status === 'active' ? 'inactive' : 'active';
        $item->update(['status' => $newStatus]);
        return back()->with('success', "Herramienta {$item->name} " . ($newStatus === 'active' ? 'activada.' : 'desactivada.'));
    }

    public function updateStock(Request $request, InventoryService $service)
    {
        abort_unless($this->canManageInventory($request->user(), 'editar'), 403);
        $data = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('status', 'active')],
            'quantity_total' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);
        if (! isset($data['quantity_available'])) {
            $data['quantity_available'] = $data['quantity_total'];
        }
        abort_if((int) $data['quantity_available'] > (int) $data['quantity_total'], 422, 'La cantidad disponible no puede superar la total.');
        $service->setStock($data['vehicle_id'], $data['inventory_item_id'], (int) $data['quantity_total'], (int) $data['quantity_available'], $request->user()->id);
        return back()->with('success', 'Inventario actualizado.');
    }

    public function removeStock(Request $request, InventoryService $service)
    {
        abort_unless($this->canManageInventory($request->user(), 'editar'), 403);
        $data = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('status', 'active')],
        ]);

        try {
            $service->removeFromVehicle((int) $data['vehicle_id'], (int) $data['inventory_item_id'], $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Herramienta retirada del vehiculo.');
    }

    private function canManageInventory($user, string $action): bool
    {
        return ! $user->hasRole('Tecnico', 'Conductor') && $user->canAccess('inventario', $action);
    }
}
