<?php
namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\ToolRequest;
use App\Models\ToolRequestDelay;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user()->load('role');
        $role = $user->role?->name ?? 'Usuario';

        if ($user->hasRole('Tecnico')) {
            $stats = [
                ['label' => 'Mis pendientes', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'pendiente')->count(), 'icon' => 'ClipboardList', 'route' => '/solicitudes?status=pendiente'],
                ['label' => 'En demora', 'value' => ToolRequestDelay::where('status', 'active')->whereHas('request', fn($q) => $q->where('technician_id', $user->id))->count(), 'icon' => 'AlertTriangle', 'route' => '/solicitudes?delay=active'],
                ['label' => 'Solicitudes vencidas', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'vencida')->count(), 'icon' => 'Clock3', 'route' => '/solicitudes?status=vencida'],
                ['label' => 'En curso', 'value' => ToolRequest::where('technician_id', $user->id)->whereIn('status', ['en_camino','en_uso','para_recoger','recogida'])->count(), 'icon' => 'MapPin', 'route' => '/solicitudes'],
                ['label' => 'Finalizadas', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'finalizada')->count(), 'icon' => 'PackageCheck', 'route' => '/solicitudes?status=finalizada'],
                ['label' => 'Mensajes sin leer', 'value' => ChatMessage::whereHas('chat.request', fn($q) => $q->where('technician_id', $user->id))->where('sender_id', '!=', $user->id)->whereNull('read_at')->count(), 'icon' => 'MessageCircle', 'route' => '/solicitudes'],
                ['label' => 'Vehiculos cercanos', 'value' => Vehicle::where('status', 'active')->count(), 'icon' => 'Car', 'route' => '/vehiculos'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver','activeDelays'])->where('technician_id', $user->id)->latest()->limit(8)->get();
        } elseif ($user->hasRole('Conductor')) {
            $stats = [
                ['label' => 'Recibidas', 'value' => ToolRequest::where('driver_id', $user->id)->count(), 'icon' => 'ClipboardList', 'route' => '/solicitudes'],
                ['label' => 'Pendientes', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'pendiente')->count(), 'icon' => 'Bell', 'route' => '/solicitudes?status=pendiente'],
                ['label' => 'En demora', 'value' => ToolRequestDelay::where('status', 'active')->whereHas('request', fn($q) => $q->where('driver_id', $user->id))->count(), 'icon' => 'AlertTriangle', 'route' => '/solicitudes?delay=active'],
                ['label' => 'Solicitudes vencidas', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'vencida')->count(), 'icon' => 'Clock3', 'route' => '/solicitudes?status=vencida'],
                ['label' => 'En camino', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'en_camino')->count(), 'icon' => 'MapPin', 'route' => '/solicitudes?status=en_camino'],
                ['label' => 'Entregas pendientes', 'value' => ToolRequest::where('driver_id', $user->id)->whereIn('status', ['pendiente','en_camino','para_recoger'])->count(), 'icon' => 'PackageCheck', 'route' => '/solicitudes'],
                ['label' => 'Mensajes sin leer', 'value' => ChatMessage::whereHas('chat.request', fn($q) => $q->where('driver_id', $user->id))->where('sender_id', '!=', $user->id)->whereNull('read_at')->count(), 'icon' => 'MessageCircle', 'route' => '/solicitudes'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver','activeDelays'])->where('driver_id', $user->id)->latest()->limit(8)->get();
        } else {
            $stats = [
                ['label' => 'Total vehiculos', 'value' => Vehicle::count(), 'icon' => 'Car', 'route' => '/vehiculos'],
                ['label' => 'Vehiculos activos', 'value' => Vehicle::where('status','active')->count(), 'icon' => 'MapPin', 'route' => '/vehiculos?status=active'],
                ['label' => 'En movimiento', 'value' => Vehicle::where('current_speed','>',0)->count(), 'icon' => 'MapPin', 'route' => '/vehiculos?movement=moving'],
                ['label' => 'Sin movimiento', 'value' => Vehicle::where('status', 'active')->where(fn ($q) => $q->whereNull('current_speed')->orWhere('current_speed', '<=', 0))->count(), 'icon' => 'Clock3', 'route' => '/vehiculos?movement=stopped'],
                ['label' => 'Total usuarios', 'value' => User::count(), 'icon' => 'Users', 'route' => '/usuarios'],
                ['label' => 'Solicitudes pendientes', 'value' => ToolRequest::where('status','pendiente')->count(), 'icon' => 'ClipboardList', 'route' => '/solicitudes?status=pendiente'],
                ['label' => 'En demora', 'value' => ToolRequestDelay::where('status', 'active')->count(), 'icon' => 'AlertTriangle', 'route' => '/solicitudes?delay=active'],
                ['label' => 'Solicitudes vencidas', 'value' => ToolRequest::where('status','vencida')->count(), 'icon' => 'Clock3', 'route' => '/solicitudes?status=vencida'],
                ['label' => 'Herramientas registradas', 'value' => InventoryItem::where('status', 'active')->count(), 'icon' => 'PackageCheck', 'route' => '/inventario'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver','activeDelays'])->latest()->limit(8)->get();
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'role' => $role,
            'notifications' => Notification::where('user_id', $user->id)->latest()->limit(5)->get(),
        ]);
    }
}
