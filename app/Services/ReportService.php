<?php

namespace App\Services;

use App\Exports\ArrayReportExport;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ToolRequest;
use App\Models\ToolRequestStatusHistory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportService
{
    public function catalog(): array
    {
        return [
            ['key' => 'vehicles', 'name' => 'Vehiculos', 'description' => 'Estado operativo, conductor asignado y ultima posicion GPS.'],
            ['key' => 'users', 'name' => 'Usuarios', 'description' => 'Usuarios, roles, estado y ultima ubicacion registrada.'],
            ['key' => 'technicians', 'name' => 'Tecnicos', 'description' => 'Detalle de tecnicos y actividad de ubicacion.'],
            ['key' => 'drivers', 'name' => 'Conductores', 'description' => 'Conductores, vehiculo asignado y estado.'],
            ['key' => 'requests', 'name' => 'Solicitudes', 'description' => 'Solicitudes de herramientas con items, estados y responsables.'],
            ['key' => 'inventory', 'name' => 'Inventario', 'description' => 'Stock por vehiculo, herramienta y categoria.'],
            ['key' => 'movements', 'name' => 'Movimientos', 'description' => 'Movimientos de inventario y saldos.'],
            ['key' => 'gps_traces', 'name' => 'Trazas GPS', 'description' => 'Historial de posiciones GPS por vehiculo.'],
            ['key' => 'audit', 'name' => 'Auditoria', 'description' => 'Acciones registradas por usuario, modulo e IP.'],
            ['key' => 'activity', 'name' => 'Actividad', 'description' => 'Historial de cambios de estado de solicitudes.'],
        ];
    }

    public function download(string $type, array $filters = []): BinaryFileResponse
    {
        [$headings, $rows] = $this->build($type, $filters);
        $file = 'colvatrack_'.$type.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ArrayReportExport($headings, $rows), $file);
    }

    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'vehicles' => $this->vehicles($filters),
            'users' => $this->users($filters),
            'technicians' => $this->users($filters, 'Tecnico'),
            'drivers' => $this->users($filters, 'Conductor'),
            'requests' => $this->requests($filters),
            'inventory' => $this->inventory($filters),
            'movements' => $this->movements($filters),
            'gps_traces' => $this->gpsTraces($filters),
            'audit' => $this->audit($filters),
            'activity' => $this->activity($filters),
            default => abort(404, 'Reporte no encontrado.'),
        };
    }

    private function vehicles(array $filters): array
    {
        $query = Vehicle::with(['driver', 'provider'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('plate');

        $headings = ['Placa', 'Marca', 'Modelo', 'Ano', 'Color', 'Estado', 'Conductor', 'Proveedor GPS', 'Latitud', 'Longitud', 'Velocidad', 'Evento GPS', 'Ultima fecha GPS', 'Direccion'];
        $rows = $query->get()->map(fn (Vehicle $v) => [
            $v->plate,
            $v->brand,
            $v->model,
            $v->year,
            $v->color,
            $v->status,
            $v->driver?->name.' '.$v->driver?->last_name,
            $v->provider?->name,
            $v->current_latitude,
            $v->current_longitude,
            $v->current_speed,
            $v->last_gps_event,
            $this->date($v->last_gps_datetime),
            $v->current_address,
        ])->all();

        return [$headings, $rows];
    }

    private function users(array $filters, ?string $roleName = null): array
    {
        $query = User::with(['role', 'assignedVehicle'])
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($roleName, fn (Builder $q) => $q->whereHas('role', fn (Builder $r) => $r->where('name', $roleName)))
            ->orderBy('name');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Nombre', 'Apellido', 'Email', 'Documento', 'Telefono', 'Rol', 'Estado', 'Vehiculo asignado', 'Ultimo login', 'Ubicacion actualizada', 'Latitud', 'Longitud'];
        $rows = $query->get()->map(fn (User $u) => [
            $u->name,
            $u->last_name,
            $u->email,
            $u->document,
            $u->phone,
            $u->role?->name,
            $u->status,
            $u->assignedVehicle?->plate,
            $this->date($u->last_login_at),
            $this->date($u->location_updated_at),
            $u->current_latitude,
            $u->current_longitude,
        ])->all();

        return [$headings, $rows];
    }

    private function requests(array $filters): array
    {
        $query = ToolRequest::with(['vehicle', 'technician', 'driver', 'items.item'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where(fn (Builder $inner) => $inner->where('technician_id', $id)->orWhere('driver_id', $id)))
            ->latest('requested_at');
        $this->dateRange($query, $filters, 'requested_at');

        $headings = ['Solicitud', 'Estado', 'Prioridad', 'Vehiculo', 'Tecnico', 'Conductor', 'Herramienta', 'Cantidad', 'Estado item', 'Fecha solicitud', 'Aceptada', 'Entregada', 'Recogida', 'Finalizada', 'Observacion'];
        $rows = [];
        foreach ($query->get() as $request) {
            $items = $request->items->isEmpty() ? collect([null]) : $request->items;
            foreach ($items as $item) {
                $rows[] = [
                    $request->id,
                    $request->status,
                    $request->priority,
                    $request->vehicle?->plate,
                    trim($request->technician?->name.' '.$request->technician?->last_name),
                    trim($request->driver?->name.' '.$request->driver?->last_name),
                    $item?->item?->name,
                    $item?->quantity,
                    $item?->status,
                    $this->date($request->requested_at),
                    $this->date($request->accepted_at),
                    $this->date($request->delivered_at),
                    $this->date($request->picked_up_at),
                    $this->date($request->finalized_at),
                    $request->observation,
                ];
            }
        }

        return [$headings, $rows];
    }

    private function inventory(array $filters): array
    {
        $items = InventoryItem::with(['category', 'vehicleInventories.vehicle.driver'])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('inventory_category_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->get();

        $headings = ['Categoria', 'Herramienta', 'Unidad', 'Estado herramienta', 'Vehiculo', 'Conductor', 'Total', 'Disponible', 'Reservado', 'Entregado', 'Estado inventario'];
        $rows = [];
        foreach ($items as $item) {
            $stocks = $item->vehicleInventories
                ->when($filters['vehicle_id'] ?? null, fn ($collection, $id) => $collection->where('vehicle_id', (int) $id));
            if ($stocks->isEmpty()) {
                $rows[] = [$item->category?->name, $item->name, $item->unit, $item->status, null, null, 0, 0, 0, 0, 'sin_stock'];
                continue;
            }
            foreach ($stocks as $stock) {
                $rows[] = [
                    $item->category?->name,
                    $item->name,
                    $item->unit,
                    $item->status,
                    $stock->vehicle?->plate,
                    trim($stock->vehicle?->driver?->name.' '.$stock->vehicle?->driver?->last_name),
                    $stock->quantity_total,
                    $stock->quantity_available,
                    $stock->quantity_reserved,
                    $stock->quantity_delivered,
                    $stock->status,
                ];
            }
        }

        return [$headings, $rows];
    }

    private function movements(array $filters): array
    {
        $query = InventoryMovement::with(['vehicle', 'item.category', 'creator'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('item', fn (Builder $item) => $item->where('inventory_category_id', $id)))
            ->when($filters['status'] ?? null, fn (Builder $q, $type) => $q->where('movement_type', $type))
            ->latest('created_at');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Fecha', 'Vehiculo', 'Categoria', 'Herramienta', 'Movimiento', 'Cantidad', 'Disponible anterior', 'Disponible nuevo', 'Solicitud', 'Creado por', 'Comentario'];
        $rows = $query->get()->map(fn (InventoryMovement $m) => [
            $this->date($m->created_at),
            $m->vehicle?->plate,
            $m->item?->category?->name,
            $m->item?->name,
            $m->movement_type,
            $m->quantity,
            $m->previous_available,
            $m->new_available,
            $m->request_id,
            trim($m->creator?->name.' '.$m->creator?->last_name),
            $m->comment,
        ])->all();

        return [$headings, $rows];
    }

    private function gpsTraces(array $filters): array
    {
        $query = VehicleLocation::with(['vehicle.driver'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->latest('gps_datetime')
            ->limit(10000);
        $this->dateRange($query, $filters, 'gps_datetime');

        $headings = ['Fecha GPS', 'Vehiculo', 'Conductor', 'Latitud', 'Longitud', 'Velocidad', 'Rumbo', 'Evento', 'Odometro', 'Direccion', 'Registrado en'];
        $rows = $query->get()->map(fn (VehicleLocation $l) => [
            $this->date($l->gps_datetime),
            $l->vehicle?->plate,
            trim($l->vehicle?->driver?->name.' '.$l->vehicle?->driver?->last_name),
            $l->latitude,
            $l->longitude,
            $l->speed,
            $l->heading,
            $l->gps_event,
            $l->odometer,
            $l->address,
            $this->date($l->created_at),
        ])->all();

        return [$headings, $rows];
    }

    private function audit(array $filters): array
    {
        $query = AuditLog::with('user')
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $module) => $q->where('module', $module))
            ->latest('created_at');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Fecha', 'Usuario', 'Accion', 'Modulo', 'Descripcion', 'IP'];
        $rows = $query->get()->map(fn (AuditLog $log) => [
            $this->date($log->created_at),
            trim($log->user?->name.' '.$log->user?->last_name),
            $log->action,
            $log->module,
            $log->description,
            $log->ip_address,
        ])->all();

        return [$headings, $rows];
    }

    private function activity(array $filters): array
    {
        $query = ToolRequestStatusHistory::with(['request.vehicle', 'user'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('request', fn (Builder $r) => $r->where('vehicle_id', $id)))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('new_status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('changed_by', $id))
            ->latest('created_at');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Fecha', 'Solicitud', 'Vehiculo', 'Estado anterior', 'Estado nuevo', 'Usuario', 'Comentario'];
        $rows = $query->get()->map(fn (ToolRequestStatusHistory $h) => [
            $this->date($h->created_at),
            $h->tool_request_id,
            $h->request?->vehicle?->plate,
            $h->old_status,
            $h->new_status,
            trim($h->user?->name.' '.$h->user?->last_name),
            $h->comment,
        ])->all();

        return [$headings, $rows];
    }

    private function dateRange(Builder $query, array $filters, string $column): void
    {
        if ($from = Arr::get($filters, 'date_from')) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to = Arr::get($filters, 'date_to')) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function date(mixed $value): ?string
    {
        return $value ? $value->format('Y-m-d H:i:s') : null;
    }
}
