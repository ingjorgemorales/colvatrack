<?php
namespace App\Services;

use App\Events\InventoryUpdated;
use App\Models\InventoryMovement;
use App\Models\VehicleInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function setStock(int $vehicleId, int $itemId, int $total, int $available, ?int $userId = null): VehicleInventory
    {
        return DB::transaction(function () use ($vehicleId, $itemId, $total, $available, $userId) {
            $row = VehicleInventory::firstOrCreate(['vehicle_id' => $vehicleId, 'inventory_item_id' => $itemId], ['quantity_total' => 0, 'quantity_available' => 0, 'quantity_reserved' => 0, 'quantity_delivered' => 0, 'status' => 'active']);
            $previous = $row->quantity_available;
            $row->update(['quantity_total' => $total, 'quantity_available' => $available, 'status' => 'active']);
            InventoryMovement::create(['vehicle_id' => $vehicleId, 'inventory_item_id' => $itemId, 'movement_type' => 'stock_update', 'quantity' => abs($available - $previous), 'previous_available' => $previous, 'new_available' => $available, 'created_by' => $userId, 'comment' => 'Actualizacion manual de inventario', 'created_at' => now()]);
            try { broadcast(new InventoryUpdated($row->vehicle))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
            return $row->fresh();
        });
    }

    public function reserve(int $vehicleId, int $itemId, int $quantity, ?int $requestId = null): VehicleInventory
    {
        return DB::transaction(function () use ($vehicleId, $itemId, $quantity, $requestId) {
            $row = $this->lockedRow($vehicleId, $itemId);
            if ($row->quantity_available < $quantity) { throw new InvalidArgumentException('No se puede solicitar mas cantidad de la disponible.'); }
            $previous = $row->quantity_available;
            $row->update(['quantity_available' => $previous - $quantity, 'quantity_reserved' => $row->quantity_reserved + $quantity]);
            $this->movement($row, 'reserved', $quantity, $previous, $previous - $quantity, $requestId, 'Reserva por solicitud');
            return $row->fresh();
        });
    }

    public function releaseReserved(int $vehicleId, int $itemId, int $quantity, ?int $requestId = null, string $comment = 'Reserva liberada'): VehicleInventory
    {
        return DB::transaction(function () use ($vehicleId, $itemId, $quantity, $requestId, $comment) {
            $row = $this->lockedRow($vehicleId, $itemId);
            $reserved = min($quantity, $row->quantity_reserved);
            $previous = $row->quantity_available;
            $row->update(['quantity_available' => $previous + $reserved, 'quantity_reserved' => $row->quantity_reserved - $reserved]);
            $this->movement($row, 'released', $reserved, $previous, $previous + $reserved, $requestId, $comment);
            return $row->fresh();
        });
    }

    public function markDelivered(int $vehicleId, int $itemId, int $quantity, ?int $requestId = null): VehicleInventory
    {
        return DB::transaction(function () use ($vehicleId, $itemId, $quantity, $requestId) {
            $row = $this->lockedRow($vehicleId, $itemId);
            $reserved = min($quantity, $row->quantity_reserved);
            $previous = $row->quantity_available;
            $row->update(['quantity_reserved' => $row->quantity_reserved - $reserved, 'quantity_delivered' => $row->quantity_delivered + $reserved]);
            $this->movement($row, 'delivered', $reserved, $previous, $previous, $requestId, 'Herramienta entregada');
            return $row->fresh();
        });
    }

    public function returnDelivered(int $vehicleId, int $itemId, int $quantity, ?int $requestId = null): VehicleInventory
    {
        return DB::transaction(function () use ($vehicleId, $itemId, $quantity, $requestId) {
            $row = $this->lockedRow($vehicleId, $itemId);
            $delivered = min($quantity, $row->quantity_delivered);
            $previous = $row->quantity_available;
            $row->update(['quantity_delivered' => $row->quantity_delivered - $delivered, 'quantity_available' => $previous + $delivered]);
            $this->movement($row, 'returned', $delivered, $previous, $previous + $delivered, $requestId, 'Herramienta recogida/devuelta');
            return $row->fresh();
        });
    }

    private function lockedRow(int $vehicleId, int $itemId): VehicleInventory
    {
        return VehicleInventory::where('vehicle_id', $vehicleId)->where('inventory_item_id', $itemId)->lockForUpdate()->firstOrFail();
    }

    private function movement(VehicleInventory $row, string $type, int $quantity, int $previous, int $new, ?int $requestId, string $comment): void
    {
        InventoryMovement::create(['vehicle_id' => $row->vehicle_id, 'inventory_item_id' => $row->inventory_item_id, 'request_id' => $requestId, 'movement_type' => $type, 'quantity' => $quantity, 'previous_available' => $previous, 'new_available' => $new, 'created_by' => auth()->id(), 'comment' => $comment, 'created_at' => now()]);
        try { broadcast(new InventoryUpdated($row->vehicle))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
    }
}
