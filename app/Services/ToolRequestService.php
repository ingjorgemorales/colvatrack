<?php
namespace App\Services;

use App\Events\ToolRequestCreated;
use App\Events\ToolRequestStatusChanged;
use App\Models\Chat;
use App\Models\ToolRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ToolRequestService
{
    public function __construct(private InventoryService $inventory, private NotificationService $notifications, private MailNotificationService $mail) {}

    public function create(array $data): ToolRequest
    {
        $items = $this->normalizeItems($data['items'] ?? []);
        $enforceSingleActiveTechnician = (bool) ($data['enforce_single_active_technician'] ?? true);
        unset($data['items'], $data['enforce_single_active_technician']);

        return DB::transaction(function () use ($data, $items, $enforceSingleActiveTechnician) {
            if ($enforceSingleActiveTechnician) {
                $activeTechnicianRequest = ToolRequest::where('technician_id', $data['technician_id'])->activeForTechnician()->lockForUpdate()->first();
                if ($activeTechnicianRequest) {
                    throw new InvalidArgumentException('Ya tienes una solicitud activa (#'.$activeTechnicianRequest->id.') en estado '.$activeTechnicianRequest->status.'. Finalizala antes de crear una nueva solicitud.');
                }
            }

            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            $activeRequest = ToolRequest::where('vehicle_id', $vehicle->id)->activeForVehicle()->lockForUpdate()->first();
            if ($activeRequest) {
                throw new InvalidArgumentException('El vehiculo '.$vehicle->plate.' no esta disponible porque tiene la solicitud #'.$activeRequest->id.' en estado '.$activeRequest->status.'.');
            }

            $data['driver_id'] = $data['driver_id'] ?? $vehicle->driver_id;
            $request = ToolRequest::create($data + ['status' => 'pendiente', 'requested_at' => now()]);
            foreach ($items as $item) {
                $inventoryRow = VehicleInventory::where('vehicle_id', $vehicle->id)
                    ->where('inventory_item_id', $item['inventory_item_id'])
                    ->whereHas('item', fn ($query) => $query->where('status', 'active'))
                    ->lockForUpdate()
                    ->first();

                if (! $inventoryRow) {
                    throw new InvalidArgumentException('La herramienta seleccionada no esta activa o no esta asignada al vehiculo.');
                }

                if ((int) $inventoryRow->quantity_available < (int) $item['quantity']) {
                    throw new InvalidArgumentException('No se puede solicitar mas cantidad de la disponible.');
                }

                $request->items()->create(['inventory_item_id' => $item['inventory_item_id'], 'quantity' => $item['quantity'], 'status' => 'reserved']);
                $this->inventory->reserve($request->vehicle_id, $item['inventory_item_id'], $item['quantity'], $request->id);
            }
            $request->histories()->create(['old_status' => null, 'new_status' => 'pendiente', 'changed_by' => $request->technician_id, 'comment' => 'Solicitud creada', 'created_at' => now()]);
            Chat::firstOrCreate(['tool_request_id' => $request->id], ['technician_id' => $request->technician_id, 'driver_id' => $request->driver_id, 'status' => 'active']);
            $request = $request->fresh(['items','chat','vehicle','technician','driver']);
            if ($request->driver) {
                $this->notifications->create($request->driver_id, 'Nueva solicitud de herramientas', 'El tecnico '.$request->technician?->name.' solicito herramientas del vehiculo '.$request->vehicle?->plate.'.', 'tool_request', ['tool_request_id' => $request->id]);
                $this->mail->sendPlain($request->driver->email, 'Nueva solicitud ColvaTrack #'.$request->id, 'Tienes una nueva solicitud de herramientas para el vehiculo '.$request->vehicle?->plate.'.');
            }
            try { broadcast(new ToolRequestCreated($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
            return $request;
        });
    }

    public function changeStatus(ToolRequest $request, string $status, int $userId, ?string $comment = null): ToolRequest
    {
        return DB::transaction(function () use ($request, $status, $userId, $comment) {
            $old = $request->status;
            $this->guardTransition($old, $status);
            $request->loadMissing('items', 'technician', 'driver', 'vehicle');

            if (in_array($status, ['rechazada', 'cancelada'], true) && in_array($old, ['pendiente', 'aceptada', 'en_camino'], true)) {
                foreach ($request->items as $item) { $this->inventory->releaseReserved($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id, 'Solicitud '.$status); $item->update(['status' => 'released']); }
            }
            if ($status === 'entregada' && $old !== 'entregada') {
                foreach ($request->items as $item) { $this->inventory->markDelivered($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id); $item->update(['status' => 'delivered']); }
            }
            if ($status === 'recogida' && $old !== 'recogida') {
                foreach ($request->items as $item) { $item->update(['status' => 'picked_up']); }
            }
            if ($status === 'finalizada' && $old !== 'finalizada') {
                foreach ($request->items as $item) { $this->inventory->returnDelivered($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id); $item->update(['status' => 'returned']); }
            }

            $timestamps = ['aceptada'=>'accepted_at','rechazada'=>'rejected_at','en_camino'=>'en_route_at','entregada'=>'delivered_at','para_recoger'=>'ready_for_pickup_at','recogida'=>'picked_up_at','finalizada'=>'finalized_at','cancelada'=>'cancelled_at'];
            $request->fill(['status' => $status]);
            if (isset($timestamps[$status])) { $request->{$timestamps[$status]} = now(); }
            $request->save();
            $request->histories()->create(['old_status' => $old, 'new_status' => $status, 'changed_by' => $userId, 'comment' => $comment, 'created_at' => now()]);
            $request = $request->fresh(['items.item','histories.user','vehicle','technician','driver']);

            $title = 'Solicitud '.$this->statusLabel($status);
            $message = 'La solicitud #'.$request->id.' cambio de '.$this->statusLabel($old).' a '.$this->statusLabel($status).' para el vehiculo '.$request->vehicle?->plate.'.';
            foreach ($this->participants($request) as $participant) {
                $this->notifications->create($participant->id, $title, $message, 'tool_request_status', ['tool_request_id' => $request->id, 'old_status' => $old, 'new_status' => $status]);
                $this->mail->sendPlain($participant->email, $title.' en ColvaTrack', $message);
            }

            try { broadcast(new ToolRequestStatusChanged($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
            return $request;
        });
    }

    public function expirePendingOlderThan(int $minutes = 30): int
    {
        $ids = ToolRequest::where('status', 'pendiente')
            ->where('requested_at', '<=', now()->subMinutes($minutes))
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            if ($this->expirePendingRequest((int) $id, $minutes)) {
                $expired++;
            }
        }

        return $expired;
    }

    public function expirePendingRequest(int $requestId, int $minutes = 30): ?ToolRequest
    {
        return DB::transaction(function () use ($requestId, $minutes) {
            $request = ToolRequest::whereKey($requestId)->lockForUpdate()->first();

            if (! $request || $request->status !== 'pendiente') {
                return null;
            }

            $requestedAt = $request->requested_at ?? $request->created_at;
            if (! $requestedAt || $requestedAt->gt(now()->subMinutes($minutes))) {
                return null;
            }

            $request->loadMissing('items', 'technician', 'driver', 'vehicle');

            foreach ($request->items as $item) {
                $this->inventory->releaseReserved($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id, 'Solicitud vencida por tiempo de aceptacion');
                $item->update(['status' => 'released']);
            }

            $request->update(['status' => 'vencida']);
            $request->histories()->create([
                'old_status' => 'pendiente',
                'new_status' => 'vencida',
                'changed_by' => $request->technician_id,
                'comment' => 'Solicitud vencida automaticamente por superar '.$minutes.' minutos sin aceptacion del conductor.',
                'created_at' => now(),
            ]);

            $request = $request->fresh(['items.item','histories.user','vehicle','technician','driver']);
            $title = 'Solicitud vencida';
            $message = 'La solicitud #'.$request->id.' del vehiculo '.$request->vehicle?->plate.' vencio porque no fue aceptada en '.$minutes.' minutos.';

            foreach ($this->participants($request) as $participant) {
                $this->notifications->create($participant->id, $title, $message, 'tool_request_status', ['tool_request_id' => $request->id, 'old_status' => 'pendiente', 'new_status' => 'vencida']);
                $this->mail->sendPlain($participant->email, $title.' en ColvaTrack', $message);
            }

            try { broadcast(new ToolRequestStatusChanged($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }

            return $request;
        });
    }

    public function allowedTransitionsFor(ToolRequest $request, User $user): array
    {
        $request->loadMissing('technician', 'driver');
        $status = $request->status;

        if ($user->hasRole('Administrador')) {
            return $this->transitions()[$status] ?? [];
        }

        if ($request->driver_id === $user->id) {
            return match ($status) {
                'pendiente' => ['aceptada', 'rechazada'],
                'aceptada' => ['en_camino'],
                'en_camino' => ['entregada'],
                'para_recoger' => ['recogida'],
                default => [],
            };
        }

        if ($request->technician_id === $user->id) {
            return match ($status) {
                'pendiente', 'aceptada', 'en_camino' => ['cancelada'],
                'entregada' => ['en_uso', 'para_recoger'],
                'en_uso' => ['para_recoger'],
                'recogida' => ['finalizada'],
                default => [],
            };
        }

        return [];
    }

    public function assertCanTransition(ToolRequest $request, User $user, string $status): void
    {
        if (! in_array($status, $this->allowedTransitionsFor($request, $user), true)) {
            throw new InvalidArgumentException('No tienes permiso para cambiar la solicitud a '.$status.'.');
        }
    }

    private function guardTransition(string $old, string $new): void
    {
        if ($old !== $new && !in_array($new, $this->transitions()[$old] ?? [], true)) { throw new InvalidArgumentException("No se permite cambiar de $old a $new."); }
    }

    private function transitions(): array
    {
        return ['pendiente' => ['aceptada', 'rechazada', 'cancelada', 'vencida'], 'aceptada' => ['en_camino', 'cancelada'], 'en_camino' => ['entregada', 'cancelada'], 'entregada' => ['en_uso', 'para_recoger'], 'en_uso' => ['para_recoger'], 'para_recoger' => ['recogida'], 'recogida' => ['finalizada'], 'rechazada' => [], 'vencida' => [], 'finalizada' => [], 'cancelada' => []];
    }

    private function participants(ToolRequest $request)
    {
        return collect([$request->driver, $request->technician])->filter()->unique('id')->values();
    }

    private function statusLabel(string $status): string
    {
        return [
            'pendiente' => 'pendiente',
            'aceptada' => 'aceptada',
            'rechazada' => 'rechazada',
            'vencida' => 'vencida',
            'en_camino' => 'en camino',
            'entregada' => 'entregada',
            'en_uso' => 'en uso',
            'para_recoger' => 'para recoger',
            'recogida' => 'recogida',
            'finalizada' => 'finalizada',
            'cancelada' => 'cancelada',
        ][$status] ?? $status;
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->groupBy(fn ($item) => (int) $item['inventory_item_id'])
            ->map(fn ($rows, $itemId) => ['inventory_item_id' => (int) $itemId, 'quantity' => (int) $rows->sum('quantity')])
            ->values()
            ->all();
    }
}
