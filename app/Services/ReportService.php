<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ToolRequest;
use App\Models\ToolRequestDelay;
use App\Models\ToolRequestStatusHistory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Models\VehicleReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportService
{
    public function catalog(): array
    {
        return [
            ['key' => 'vehicles', 'name' => 'Vehiculos', 'description' => 'Estado operativo, conductor asignado y ultima posicion GPS.'],
            ['key' => 'projects', 'name' => 'Proyectos', 'description' => 'Proyectos creados, estado y vehiculos asociados.'],
            ['key' => 'vehicle_reservations', 'name' => 'Reservas de vehiculos', 'description' => 'Reservas administrativas de vehiculos y liberaciones.'],
            ['key' => 'users', 'name' => 'Usuarios', 'description' => 'Usuarios, roles, estado y ultima ubicacion registrada.'],
            ['key' => 'technicians', 'name' => 'Tecnicos', 'description' => 'Detalle de tecnicos y actividad de ubicacion.'],
            ['key' => 'drivers', 'name' => 'Conductores', 'description' => 'Conductores, vehiculo asignado y estado.'],
            ['key' => 'requests', 'name' => 'Solicitudes', 'description' => 'Solicitudes de herramientas con items, estados y responsables.'],
            ['key' => 'request_delays', 'name' => 'Demoras', 'description' => 'Solicitudes vencidas o en demora con motivo, tiempo y resolucion.'],
            ['key' => 'inventory', 'name' => 'Inventario', 'description' => 'Stock por vehiculo, herramienta y categoria.'],
            ['key' => 'movements', 'name' => 'Movimientos', 'description' => 'Movimientos de inventario y saldos.'],
            ['key' => 'gps_traces', 'name' => 'Trazas GPS', 'description' => 'Historial de posiciones GPS por vehiculo.'],
            ['key' => 'notifications', 'name' => 'Notificaciones', 'description' => 'Registro de notificaciones por usuario, tipo y estado de lectura.'],
            ['key' => 'audit', 'name' => 'Auditoria', 'description' => 'Acciones registradas por usuario, modulo e IP.'],
            ['key' => 'activity', 'name' => 'Actividad', 'description' => 'Historial de cambios de estado de solicitudes.'],
        ];
    }

    public function download(string $type, array $filters = [])
    {
        $filters = $this->cleanFilters($filters);
        [$headings, $rows] = $this->build($type, $filters);
        $filename = 'colvatrack_'.$type.'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($headings, $rows, $type) {
            $spreadsheet = $this->spreadsheet($this->reportName($type), $headings, $rows);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'vehicles' => $this->vehicles($filters),
            'projects' => $this->projects($filters),
            'vehicle_reservations' => $this->vehicleReservations($filters),
            'users' => $this->users($filters),
            'technicians' => $this->users($filters, 'Tecnico'),
            'drivers' => $this->users($filters, 'Conductor'),
            'requests' => $this->requests($filters),
            'request_delays' => $this->requestDelays($filters),
            'inventory' => $this->inventory($filters),
            'movements' => $this->movements($filters),
            'gps_traces' => $this->gpsTraces($filters),
            'notifications' => $this->notifications($filters),
            'audit' => $this->audit($filters),
            'activity' => $this->activity($filters),
            default => abort(404, 'Reporte no encontrado.'),
        };
    }

    private function vehicles(array $filters): array
    {
        $query = Vehicle::with(['driver', 'provider', 'project', 'activeReservation.reservedBy'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('plate');
        $this->dateRange($query, $filters, 'last_gps_datetime');

        $headings = ['Placa', 'Marca', 'Modelo', 'Proyecto', 'Ano', 'Color', 'Estado', 'Reservado', 'Motivo reserva', 'Reservado por', 'Conductor', 'Proveedor GPS', 'Latitud', 'Longitud', 'Velocidad', 'Evento GPS', 'Ultima fecha GPS', 'Direccion'];
        $rows = $query->get()->map(fn (Vehicle $v) => [
            $v->plate,
            $v->brand,
            $v->model,
            $v->project?->name,
            $v->year,
            $v->color,
            $v->status,
            $v->activeReservation ? 'si' : 'no',
            $v->activeReservation?->reason,
            trim($v->activeReservation?->reservedBy?->name.' '.$v->activeReservation?->reservedBy?->last_name),
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

    private function projects(array $filters): array
    {
        $query = Project::with(['vehicles.driver'])
            ->withCount('vehicles')
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('name');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Proyecto', 'Descripcion', 'Estado', 'Vehiculos asociados', 'Placas', 'Conductores', 'Creado en', 'Actualizado en'];
        $rows = $query->get()->map(fn (Project $project) => [
            $project->name,
            $project->description,
            $project->status,
            $project->vehicles_count,
            $project->vehicles->pluck('plate')->filter()->join(', '),
            $project->vehicles->map(fn (Vehicle $vehicle) => trim($vehicle->driver?->name.' '.$vehicle->driver?->last_name))->filter()->join(', '),
            $this->date($project->created_at),
            $this->date($project->updated_at),
        ])->all();

        return [$headings, $rows];
    }

    private function vehicleReservations(array $filters): array
    {
        $query = VehicleReservation::with(['vehicle.project', 'reservedBy'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('project_id', $id)))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('reserved_by', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest('starts_at');
        $this->dateRange($query, $filters, 'starts_at');

        $headings = ['Vehiculo', 'Proyecto', 'Estado reserva', 'Motivo', 'Reservado por', 'Inicio', 'Fin programado', 'Liberado en', 'Comentario liberacion', 'Creado en'];
        $rows = $query->get()->map(fn (VehicleReservation $reservation) => [
            $reservation->vehicle?->plate,
            $reservation->vehicle?->project?->name,
            $reservation->status,
            $reservation->reason,
            trim($reservation->reservedBy?->name.' '.$reservation->reservedBy?->last_name),
            $this->date($reservation->starts_at),
            $this->date($reservation->ends_at),
            $this->date($reservation->released_at),
            $reservation->release_comment,
            $this->date($reservation->created_at),
        ])->all();

        return [$headings, $rows];
    }

    private function users(array $filters, ?string $roleName = null): array
    {
        $query = User::with(['role', 'assignedVehicle'])
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['role_id'] ?? null, fn (Builder $q, $id) => $q->where('role_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($roleName, fn (Builder $q) => $q->whereHas('role', fn (Builder $r) => $r->where('name', $roleName)))
            ->orderBy('name');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Nombre', 'Apellido', 'Email', 'Cedula', 'Telefono', 'Cargo', 'Rol', 'Estado', 'Vehiculo asignado', 'Ultimo login', 'Ubicacion actualizada', 'Latitud', 'Longitud'];
        $rows = $query->get()->map(fn (User $u) => [
            $u->name,
            $u->last_name,
            $u->email,
            $u->cedula,
            $u->phone,
            $u->cargo,
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
        $query = ToolRequest::with(['vehicle.project', 'technician', 'driver', 'items.item', 'activeDelays'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('project_id', $id)))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where(fn (Builder $inner) => $inner->where('technician_id', $id)->orWhere('driver_id', $id)))
            ->latest('requested_at');
        $this->dateRange($query, $filters, 'requested_at');

        $headings = ['Solicitud', 'Estado', 'Prioridad', 'Vehiculo', 'Proyecto', 'Tiene demora activa', 'Motivo demora activa', 'Tecnico', 'Conductor', 'Herramienta', 'Cantidad', 'Estado item', 'Fecha solicitud', 'Aceptada', 'Entregada', 'Lista para recoger', 'Recogida', 'Finalizada', 'Cancelada', 'Observacion'];
        $rows = [];
        foreach ($query->get() as $request) {
            $items = $request->items->isEmpty() ? collect([null]) : $request->items;
            foreach ($items as $item) {
                $rows[] = [
                    $request->id,
                    $request->status,
                    $request->priority,
                    $request->vehicle?->plate,
                    $request->vehicle?->project?->name,
                    $request->activeDelays->isNotEmpty() ? 'si' : 'no',
                    $request->activeDelays->pluck('reason')->join(' | '),
                    trim($request->technician?->name.' '.$request->technician?->last_name),
                    trim($request->driver?->name.' '.$request->driver?->last_name),
                    $item?->item?->name,
                    $item?->quantity,
                    $item?->status,
                    $this->date($request->requested_at),
                    $this->date($request->accepted_at),
                    $this->date($request->delivered_at),
                    $this->date($request->ready_for_pickup_at),
                    $this->date($request->picked_up_at),
                    $this->date($request->finalized_at),
                    $this->date($request->cancelled_at),
                    $request->observation,
                ];
            }
        }

        return [$headings, $rows];
    }

    private function requestDelays(array $filters): array
    {
        $query = ToolRequestDelay::with(['request.vehicle.project', 'request.technician', 'request.driver'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('request', fn (Builder $request) => $request->where('vehicle_id', $id)))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('request.vehicle', fn (Builder $vehicle) => $vehicle->where('project_id', $id)))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('request', fn (Builder $request) => $request->where(fn (Builder $inner) => $inner->where('technician_id', $id)->orWhere('driver_id', $id))))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest('detected_at');
        $this->dateRange($query, $filters, 'detected_at');

        $headings = ['Detectada en', 'Solicitud', 'Vehiculo', 'Proyecto', 'Tecnico', 'Conductor', 'Tipo', 'Estado demora', 'Estado solicitud al detectar', 'Minutos permitidos', 'Minutos transcurridos', 'Inicio del estado', 'Resuelta en', 'Motivo'];
        $rows = $query->get()->map(fn (ToolRequestDelay $delay) => [
            $this->date($delay->detected_at),
            $delay->tool_request_id,
            $delay->request?->vehicle?->plate,
            $delay->request?->vehicle?->project?->name,
            trim($delay->request?->technician?->name.' '.$delay->request?->technician?->last_name),
            trim($delay->request?->driver?->name.' '.$delay->request?->driver?->last_name),
            $delay->type,
            $delay->status,
            $delay->status_at_detection,
            $delay->allowed_minutes,
            $delay->elapsed_minutes,
            $this->date($delay->state_started_at),
            $this->date($delay->resolved_at),
            $delay->reason,
        ])->all();

        return [$headings, $rows];
    }

    private function inventory(array $filters): array
    {
        $items = InventoryItem::with(['category', 'vehicleInventories.vehicle.driver', 'vehicleInventories.vehicle.project'])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('inventory_category_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->get();

        $headings = ['Categoria', 'Herramienta', 'Unidad', 'Estado herramienta', 'Vehiculo', 'Proyecto', 'Conductor', 'Total', 'Disponible', 'Reservado', 'Entregado', 'Estado inventario'];
        $rows = [];
        foreach ($items as $item) {
            $stocks = $item->vehicleInventories
                ->when($filters['vehicle_id'] ?? null, fn ($collection, $id) => $collection->where('vehicle_id', (int) $id))
                ->when($filters['project_id'] ?? null, fn ($collection, $id) => $collection->filter(fn ($stock) => (int) $stock->vehicle?->project_id === (int) $id));
            if ($stocks->isEmpty()) {
                $rows[] = [$item->category?->name, $item->name, $item->unit, $item->status, null, null, null, 0, 0, 0, 0, 'sin_stock'];
                continue;
            }
            foreach ($stocks as $stock) {
                $rows[] = [
                    $item->category?->name,
                    $item->name,
                    $item->unit,
                    $item->status,
                    $stock->vehicle?->plate,
                    $stock->vehicle?->project?->name,
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
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('project_id', $id)))
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
        $query = VehicleLocation::with(['vehicle.driver', 'vehicle.project'])
            ->when($filters['vehicle_id'] ?? null, fn (Builder $q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('project_id', $id)))
            ->latest('gps_datetime')
            ->limit(10000);
        $this->dateRange($query, $filters, 'gps_datetime');

        $headings = ['Fecha GPS', 'Vehiculo', 'Proyecto', 'Conductor', 'Latitud', 'Longitud', 'Velocidad', 'Rumbo', 'Evento', 'Odometro', 'Direccion', 'Registrado en'];
        $rows = $query->get()->map(fn (VehicleLocation $l) => [
            $this->date($l->gps_datetime),
            $l->vehicle?->plate,
            $l->vehicle?->project?->name,
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

    private function notifications(array $filters): array
    {
        $query = Notification::with('user')
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when(($filters['read_status'] ?? null) === 'read', fn (Builder $q) => $q->whereNotNull('read_at'))
            ->when(($filters['read_status'] ?? null) === 'unread', fn (Builder $q) => $q->whereNull('read_at'))
            ->latest('created_at');
        $this->dateRange($query, $filters, 'created_at');

        $headings = ['Fecha', 'Usuario', 'Email', 'Tipo', 'Titulo', 'Mensaje', 'Estado lectura', 'Leida en', 'Datos'];
        $rows = $query->get()->map(fn (Notification $notification) => [
            $this->date($notification->created_at),
            trim($notification->user?->name.' '.$notification->user?->last_name),
            $notification->user?->email,
            $notification->type,
            $notification->title,
            $notification->message,
            $notification->read_at ? 'leida' : 'no leida',
            $this->date($notification->read_at),
            $notification->data_json ? json_encode($notification->data_json, JSON_UNESCAPED_UNICODE) : null,
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

    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function reportName(string $type): string
    {
        return collect($this->catalog())->firstWhere('key', $type)['name'] ?? 'Reporte';
    }

    private function spreadsheet(string $title, array $headings, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($title));
        $sheet->fromArray($headings, null, 'A1', true);
        $sheet->fromArray($rows, null, 'A2', true);

        $highestColumn = $sheet->getHighestColumn();
        $highestRow = max(1, $sheet->getHighestRow());
        $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$highestColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE6EEF7');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function sheetTitle(string $title): string
    {
        return mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?: 'Reporte', 0, 31);
    }
}
