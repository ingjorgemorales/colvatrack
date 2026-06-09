<?php

namespace App\Console\Commands;

use App\Models\GpsProvider;
use App\Models\Notification;
use App\Models\ToolRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Services\MailNotificationService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendOperationalAlerts extends Command
{
    protected $signature = 'alerts:operational {--dry-run : Muestra las alertas que se generarian sin guardarlas ni enviar correos}';
    protected $description = 'Genera alertas operativas por GPS vencido, solicitudes represadas e inventario bajo.';

    private array $alertConfig = [];

    public function handle(NotificationService $notifications, MailNotificationService $mail): int
    {
        $this->alertConfig = $this->loadAlertConfig();

        if (! (bool) data_get($this->alertConfig, 'enabled', true)) {
            $this->info('Alertas operativas desactivadas en configuracion GPS.');
            return self::SUCCESS;
        }

        $admins = User::whereHas('role', fn ($query) => $query->where('name', 'Administrador'))->where('status', 'active')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay administradores activos para notificar.');
            return self::SUCCESS;
        }

        $created = 0;
        $created += $this->gpsAlerts($admins, $notifications, $mail);
        $created += $this->requestAlerts($admins, $notifications, $mail);
        $created += $this->inventoryAlerts($admins, $notifications, $mail);

        $label = $this->option('dry-run') ? 'Alertas operativas detectadas' : 'Alertas operativas creadas';
        $this->info($label.': '.$created);

        return self::SUCCESS;
    }

    private function gpsAlerts(Collection $admins, NotificationService $notifications, MailNotificationService $mail): int
    {
        $minutes = $this->alertInt('gps_stale_after_minutes', (int) env('GPS_STALE_AFTER_MINUTES', 15));
        $vehicles = Vehicle::where('status', 'active')
            ->where(fn ($query) => $query->whereNull('last_gps_datetime')->orWhere('last_gps_datetime', '<', now()->subMinutes($minutes)))
            ->orderBy('plate')
            ->limit(20)
            ->get(['id', 'plate', 'last_gps_datetime']);

        if ($vehicles->isEmpty() || $this->recentlySent('gps_stale_summary')) {
            return 0;
        }

        $plates = $vehicles->pluck('plate')->join(', ');
        $message = 'Vehiculos sin GPS actualizado hace mas de '.$minutes.' minutos: '.$plates.'.';

        return $this->notifyAdmins($admins, $notifications, $mail, 'Vehiculos sin GPS reciente', $message, 'gps_stale_summary', ['vehicle_ids' => $vehicles->pluck('id')->all(), 'minutes' => $minutes]);
    }

    private function requestAlerts(Collection $admins, NotificationService $notifications, MailNotificationService $mail): int
    {
        $pendingMinutes = $this->alertInt('request_pending_alert_minutes', (int) env('REQUEST_PENDING_ALERT_MINUTES', 30));
        $routeMinutes = $this->alertInt('request_en_route_alert_minutes', (int) env('REQUEST_EN_ROUTE_ALERT_MINUTES', 60));

        $pending = ToolRequest::where('status', 'pendiente')->where('requested_at', '<', now()->subMinutes($pendingMinutes))->count();
        $enRoute = ToolRequest::where('status', 'en_camino')->where('en_route_at', '<', now()->subMinutes($routeMinutes))->count();

        if (($pending + $enRoute) === 0 || $this->recentlySent('request_delay_summary')) {
            return 0;
        }

        $message = 'Solicitudes represadas: '.$pending.' pendientes por mas de '.$pendingMinutes.' min y '.$enRoute.' en camino por mas de '.$routeMinutes.' min.';

        return $this->notifyAdmins($admins, $notifications, $mail, 'Solicitudes requieren seguimiento', $message, 'request_delay_summary', ['pending' => $pending, 'en_route' => $enRoute]);
    }

    private function inventoryAlerts(Collection $admins, NotificationService $notifications, MailNotificationService $mail): int
    {
        $threshold = $this->alertInt('inventory_low_stock_threshold', (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 1));
        $rows = VehicleInventory::with(['vehicle', 'item'])
            ->where('quantity_total', '>', 0)
            ->where('quantity_available', '<=', $threshold)
            ->orderBy('quantity_available')
            ->limit(20)
            ->get();

        if ($rows->isEmpty() || $this->recentlySent('low_stock_summary')) {
            return 0;
        }

        $items = $rows->map(fn ($row) => $row->vehicle?->plate.' - '.$row->item?->name.' ('.$row->quantity_available.')')->join(', ');
        $message = 'Inventario bajo o agotado: '.$items.'.';

        return $this->notifyAdmins($admins, $notifications, $mail, 'Inventario bajo', $message, 'low_stock_summary', ['threshold' => $threshold, 'stock_ids' => $rows->pluck('id')->all()]);
    }

    private function recentlySent(string $type): bool
    {
        $minutes = $this->alertInt('repeat_minutes', (int) env('OPERATIONAL_ALERT_REPEAT_MINUTES', 60));

        return Notification::where('type', $type)->where('created_at', '>=', now()->subMinutes($minutes))->exists();
    }

    private function notifyAdmins(Collection $admins, NotificationService $notifications, MailNotificationService $mail, string $title, string $message, string $type, array $data): int
    {
        $created = 0;
        if ($this->option('dry-run')) {
            $this->line('[dry-run] '.$title.': '.$message);
            return $admins->count();
        }

        foreach ($admins as $admin) {
            $notifications->create($admin->id, $title, $message, $type, $data);
            if ($this->emailEnabled()) {
                $mail->sendPlain($admin->email, $title.' - ColvaTrack', $message);
            }
            $created++;
        }

        return $created;
    }

    private function loadAlertConfig(): array
    {
        $provider = GpsProvider::where('status', 'active')->first();
        $defaults = [
            'enabled' => true,
            'email_enabled' => filter_var(env('OPERATIONAL_ALERTS_EMAIL', false), FILTER_VALIDATE_BOOLEAN),
            'gps_stale_after_minutes' => (int) env('GPS_STALE_AFTER_MINUTES', 15),
            'request_pending_alert_minutes' => (int) env('REQUEST_PENDING_ALERT_MINUTES', 30),
            'request_en_route_alert_minutes' => (int) env('REQUEST_EN_ROUTE_ALERT_MINUTES', 60),
            'inventory_low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 1),
            'repeat_minutes' => (int) env('OPERATIONAL_ALERT_REPEAT_MINUTES', 60),
        ];

        return array_replace($defaults, (array) data_get($provider?->config_json, 'alerts', []));
    }

    private function alertInt(string $key, int $default): int
    {
        return max(1, (int) data_get($this->alertConfig, $key, $default));
    }

    private function emailEnabled(): bool
    {
        return filter_var(data_get($this->alertConfig, 'email_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}