<?php

namespace App\Services;

use App\Events\ToolRequestCreated;
use App\Events\ToolRequestStatusChanged;
use App\Models\Chat;
use App\Models\ToolRequest;
use App\Models\ToolRequestDelay;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ToolRequestService
{
    private const DELIVERY_RADIUS_METERS = 50;

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
            $this->history($request, null, 'pendiente', $request->technician_id, 'Solicitud creada');
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
            $request->loadMissing('items', 'technician', 'driver', 'vehicle', 'activeDelays');

            if ($status === 'en_uso' && $old === 'en_camino') {
                $this->assertVehicleNearTechnician($request);
            }

            if ($status === 'cancelada') {
                $this->releaseResourcesForClosure($request, $old, 'Solicitud cancelada');
            }

            if ($status === 'en_uso' && ! in_array($old, ['en_uso', 'entregada'], true)) {
                foreach ($request->items as $item) {
                    $this->inventory->markDelivered($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id);
                    $item->update(['status' => 'delivered']);
                }
                $distance = $this->vehicleTechnicianDistance($request);
                $request->fill([
                    'delivery_vehicle_latitude' => $request->vehicle?->current_latitude,
                    'delivery_vehicle_longitude' => $request->vehicle?->current_longitude,
                    'delivery_technician_latitude' => $request->technician_latitude,
                    'delivery_technician_longitude' => $request->technician_longitude,
                    'delivery_distance_meters' => $distance,
                ]);
            }

            if ($status === 'recogida' && $old !== 'recogida') {
                foreach ($request->items as $item) {
                    $item->update(['status' => 'picked_up']);
                }
            }

            if ($status === 'finalizada' && $old !== 'finalizada') {
                $this->returnDeliveredTools($request);
            }

            $request->fill(['status' => $status]);
            $this->applyStatusTimestamps($request, $old, $status);
            $request->save();
            $this->resolveDelaysForStatus($request, $old);
            $this->history($request, $old, $status, $userId, $comment);
            $request = $request->fresh(['items.item','histories.user','vehicle','technician','driver','activeDelays']);

            $this->notifyStatusChange($request, $old, $status);
            try { broadcast(new ToolRequestStatusChanged($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
            return $request;
        });
    }

    public function expirePendingOlderThan(int $minutes = 12): int
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

    public function expirePendingRequest(int $requestId, int $minutes = 12): ?ToolRequest
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
            $this->history($request, 'pendiente', 'vencida', $request->technician_id, 'Solicitud vencida automaticamente por superar '.$minutes.' minutos sin aceptacion del conductor.');

            $request = $request->fresh(['items.item','histories.user','vehicle','technician','driver','activeDelays']);
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

    public function detectActiveDelays(): int
    {
        $created = 0;
        $created += $this->detectDelay('delivery_delay', 'en_camino', 'en_route_at', 35, 'Han transcurrido mas de 35 minutos desde que el conductor acepto la solicitud y la herramienta todavia no ha sido entregada al tecnico.');
        $created += $this->detectDelay('usage_delay', 'en_uso', 'delivered_at', 45, 'La herramienta lleva mas de 45 minutos en uso y todavia no ha sido puesta a disposicion del conductor para su recogida.');
        $created += $this->detectDelay('pickup_delay', 'para_recoger', 'ready_for_pickup_at', 5, 'Han transcurrido mas de 5 minutos desde que el tecnico indico que la herramienta estaba lista para recoger y el conductor aun no ha confirmado la recogida.');

        return $created;
    }

    public function autoFinalizePickedUpOlderThan(int $minutes = 2): int
    {
        $ids = ToolRequest::where('status', 'recogida')
            ->where('picked_up_at', '<=', now()->subMinutes($minutes))
            ->pluck('id');

        $finalized = 0;
        foreach ($ids as $id) {
            if ($this->autoFinalizeRequest((int) $id, $minutes)) {
                $finalized++;
            }
        }

        return $finalized;
    }

    public function autoFinalizeRequest(int $requestId, int $minutes = 2): ?ToolRequest
    {
        return DB::transaction(function () use ($requestId, $minutes) {
            $request = ToolRequest::whereKey($requestId)->lockForUpdate()->first();

            if (! $request || $request->status !== 'recogida' || ! $request->picked_up_at || $request->picked_up_at->gt(now()->subMinutes($minutes))) {
                return null;
            }

            $request->loadMissing('items', 'technician', 'driver', 'vehicle');
            $this->returnDeliveredTools($request);
            $request->fill(['status' => 'finalizada', 'finalized_at' => now()]);
            $request->save();
            $this->resolveDelaysForStatus($request, 'recogida');
            $this->history($request, 'recogida', 'finalizada', $request->technician_id, 'La solicitud fue finalizada automaticamente por el sistema al superar los '.$minutes.' minutos despues de que el conductor confirmo la recogida de la herramienta.');

            $request = $request->fresh(['items.item','histories.user','vehicle','technician','driver','activeDelays']);
            $this->notifyStatusChange($request, 'recogida', 'finalizada');
            try { broadcast(new ToolRequestStatusChanged($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }

            return $request;
        });
    }

    public function allowedTransitionsFor(ToolRequest $request, User $user): array
    {
        $request->loadMissing('technician', 'driver');
        $status = $request->status;

        if ($user->hasRole('Administrador')) {
            return $this->adminTransitions()[$status] ?? [];
        }

        if ($request->driver_id === $user->id) {
            return match ($status) {
                'pendiente' => ['en_camino'],
                'en_camino' => ['en_uso'],
                'para_recoger' => ['recogida'],
                default => [],
            };
        }

        if ($request->technician_id === $user->id) {
            return match ($status) {
                'pendiente', 'en_camino', 'para_recoger' => ['cancelada'],
                'entregada' => ['en_uso', 'para_recoger', 'cancelada'],
                'en_uso' => ['para_recoger', 'cancelada'],
                'recogida' => ['finalizada'],
                default => [],
            };
        }

        return [];
    }

    public function assertCanTransition(ToolRequest $request, User $user, string $status): void
    {
        if (! in_array($status, $this->allowedTransitionsFor($request, $user), true)) {
            throw new InvalidArgumentException('No tienes permiso para cambiar la solicitud a '.$this->statusLabel($status).'.');
        }
    }

    public function vehicleTechnicianDistance(ToolRequest $request): ?int
    {
        $request->loadMissing('vehicle');
        if (! $request->vehicle?->current_latitude || ! $request->vehicle?->current_longitude || ! $request->technician_latitude || ! $request->technician_longitude) {
            return null;
        }

        return $this->distanceMeters(
            (float) $request->vehicle->current_latitude,
            (float) $request->vehicle->current_longitude,
            (float) $request->technician_latitude,
            (float) $request->technician_longitude
        );
    }

    private function assertVehicleNearTechnician(ToolRequest $request): void
    {
        $distance = $this->vehicleTechnicianDistance($request);

        if ($distance === null) {
            throw new InvalidArgumentException('No se puede validar la entrega porque falta ubicacion GPS del vehiculo o del tecnico.');
        }

        if ($distance > self::DELIVERY_RADIUS_METERS) {
            throw new InvalidArgumentException('El vehiculo debe estar a menos de '.self::DELIVERY_RADIUS_METERS.' metros del tecnico para registrar la entrega. Distancia actual: '.$distance.' metros.');
        }
    }

    private function releaseResourcesForClosure(ToolRequest $request, string $oldStatus, string $comment): void
    {
        if (in_array($oldStatus, ['pendiente', 'aceptada', 'en_camino'], true)) {
            foreach ($request->items as $item) {
                $this->inventory->releaseReserved($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id, $comment);
                $item->update(['status' => 'released']);
            }
            return;
        }

        if (in_array($oldStatus, ['entregada', 'en_uso', 'para_recoger', 'recogida'], true)) {
            $this->returnDeliveredTools($request);
        }
    }

    private function returnDeliveredTools(ToolRequest $request): void
    {
        foreach ($request->items as $item) {
            $this->inventory->returnDelivered($request->vehicle_id, $item->inventory_item_id, $item->quantity, $request->id);
            $item->update(['status' => 'returned']);
        }
    }

    private function applyStatusTimestamps(ToolRequest $request, string $old, string $status): void
    {
        if ($status === 'en_camino') {
            if ($old === 'pendiente' && ! $request->accepted_at) {
                $request->accepted_at = now();
            }
            $request->en_route_at = now();
        }

        if ($status === 'en_uso') {
            $request->delivered_at = now();
        }

        if ($status === 'para_recoger') {
            $request->ready_for_pickup_at = now();
        }

        if ($status === 'recogida') {
            $request->picked_up_at = now();
        }

        if ($status === 'finalizada') {
            $request->finalized_at = now();
        }

        if ($status === 'cancelada') {
            $request->cancelled_at = now();
        }
    }

    private function detectDelay(string $type, string $status, string $startedAtColumn, int $allowedMinutes, string $reason): int
    {
        $requests = ToolRequest::with(['activeDelays', 'vehicle', 'technician', 'driver'])
            ->where('status', $status)
            ->whereNotNull($startedAtColumn)
            ->where($startedAtColumn, '<=', now()->subMinutes($allowedMinutes))
            ->whereDoesntHave('delays', fn ($query) => $query->where('type', $type))
            ->get();

        $created = 0;
        foreach ($requests as $request) {
            $startedAt = $request->{$startedAtColumn};
            $elapsed = $startedAt ? max($allowedMinutes, (int) $startedAt->diffInMinutes(now())) : $allowedMinutes;

            ToolRequestDelay::create([
                'tool_request_id' => $request->id,
                'type' => $type,
                'status_at_detection' => $status,
                'allowed_minutes' => $allowedMinutes,
                'elapsed_minutes' => $elapsed,
                'reason' => $reason,
                'status' => 'active',
                'state_started_at' => $startedAt,
                'detected_at' => now(),
            ]);

            $this->history($request, $status, $status, $request->technician_id, 'En demora: '.$reason);
            $this->notifyDelay($request, $reason);
            $created++;
        }

        return $created;
    }

    private function resolveDelaysForStatus(ToolRequest $request, string $oldStatus): void
    {
        ToolRequestDelay::where('tool_request_id', $request->id)
            ->where('status', 'active')
            ->where('status_at_detection', $oldStatus)
            ->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
    }

    private function guardTransition(string $old, string $new): void
    {
        if ($old !== $new && ! in_array($new, $this->transitions()[$old] ?? [], true)) {
            throw new InvalidArgumentException('No se permite cambiar de '.$this->statusLabel($old).' a '.$this->statusLabel($new).'.');
        }
    }

    private function transitions(): array
    {
        return [
            'pendiente' => ['en_camino', 'cancelada'],
            'aceptada' => ['en_camino', 'cancelada'],
            'en_camino' => ['en_uso', 'cancelada'],
            'entregada' => ['en_uso', 'para_recoger', 'cancelada'],
            'en_uso' => ['para_recoger', 'cancelada'],
            'para_recoger' => ['recogida', 'cancelada'],
            'recogida' => ['finalizada', 'cancelada'],
            'rechazada' => [],
            'vencida' => [],
            'finalizada' => [],
            'cancelada' => [],
        ];
    }

    private function adminTransitions(): array
    {
        return $this->transitions();
    }

    private function history(ToolRequest $request, ?string $old, string $new, int $userId, ?string $comment): void
    {
        $request->histories()->create([
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $userId,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    private function notifyStatusChange(ToolRequest $request, string $old, string $status): void
    {
        $title = 'Solicitud '.$this->statusLabel($status);
        $message = 'La solicitud #'.$request->id.' cambio de '.$this->statusLabel($old).' a '.$this->statusLabel($status).' para el vehiculo '.$request->vehicle?->plate.'.';
        foreach ($this->participants($request) as $participant) {
            $this->notifications->create($participant->id, $title, $message, 'tool_request_status', ['tool_request_id' => $request->id, 'old_status' => $old, 'new_status' => $status]);
            $this->mail->sendPlain($participant->email, $title.' en ColvaTrack', $message);
        }
    }

    private function notifyDelay(ToolRequest $request, string $reason): void
    {
        $request->loadMissing('vehicle', 'technician', 'driver');
        foreach ($this->participants($request) as $participant) {
            $this->notifications->create($participant->id, 'Solicitud en demora', 'La solicitud #'.$request->id.' del vehiculo '.$request->vehicle?->plate.' esta en demora. '.$reason, 'tool_request_delay', ['tool_request_id' => $request->id]);
        }
        try { broadcast(new ToolRequestStatusChanged($request))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
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
            'para_recoger' => 'lista para recoger',
            'recogida' => 'herramienta recogida',
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

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $radius = 6371000;
        $aLat = deg2rad($lat1);
        $bLat = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat / 2) ** 2 + cos($aLat) * cos($bLat) * sin($deltaLng / 2) ** 2;

        return (int) round($radius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
