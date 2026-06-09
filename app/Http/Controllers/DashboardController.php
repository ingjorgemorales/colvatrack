<?php
namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\ToolRequest;
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
                ['label' => 'Mis pendientes', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'pendiente')->count(), 'icon' => 'ClipboardList'],
                ['label' => 'Aceptadas', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'aceptada')->count(), 'icon' => 'CheckCircle'],
                ['label' => 'En curso', 'value' => ToolRequest::where('technician_id', $user->id)->whereIn('status', ['en_camino','entregada','en_uso'])->count(), 'icon' => 'MapPin'],
                ['label' => 'Finalizadas', 'value' => ToolRequest::where('technician_id', $user->id)->where('status', 'finalizada')->count(), 'icon' => 'PackageCheck'],
                ['label' => 'Mensajes sin leer', 'value' => ChatMessage::whereHas('chat.request', fn($q) => $q->where('technician_id', $user->id))->where('sender_id', '!=', $user->id)->whereNull('read_at')->count(), 'icon' => 'MessageCircle'],
                ['label' => 'Vehiculos cercanos', 'value' => Vehicle::where('status', 'active')->count(), 'icon' => 'Car'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver'])->where('technician_id', $user->id)->latest()->limit(8)->get();
        } elseif ($user->hasRole('Conductor')) {
            $stats = [
                ['label' => 'Recibidas', 'value' => ToolRequest::where('driver_id', $user->id)->count(), 'icon' => 'ClipboardList'],
                ['label' => 'Pendientes', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'pendiente')->count(), 'icon' => 'Bell'],
                ['label' => 'Aceptadas', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'aceptada')->count(), 'icon' => 'CheckCircle'],
                ['label' => 'En camino', 'value' => ToolRequest::where('driver_id', $user->id)->where('status', 'en_camino')->count(), 'icon' => 'MapPin'],
                ['label' => 'Entregas pendientes', 'value' => ToolRequest::where('driver_id', $user->id)->whereIn('status', ['aceptada','en_camino'])->count(), 'icon' => 'PackageCheck'],
                ['label' => 'Mensajes sin leer', 'value' => ChatMessage::whereHas('chat.request', fn($q) => $q->where('driver_id', $user->id))->where('sender_id', '!=', $user->id)->whereNull('read_at')->count(), 'icon' => 'MessageCircle'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver'])->where('driver_id', $user->id)->latest()->limit(8)->get();
        } else {
            $stats = [
                ['label' => 'Total vehiculos', 'value' => Vehicle::count(), 'icon' => 'Car'],
                ['label' => 'Vehiculos activos', 'value' => Vehicle::where('status','active')->count(), 'icon' => 'MapPin'],
                ['label' => 'En movimiento', 'value' => Vehicle::where('current_speed','>',0)->count(), 'icon' => 'MapPin'],
                ['label' => 'Total usuarios', 'value' => User::count(), 'icon' => 'Users'],
                ['label' => 'Solicitudes pendientes', 'value' => ToolRequest::where('status','pendiente')->count(), 'icon' => 'ClipboardList'],
                ['label' => 'Herramientas registradas', 'value' => InventoryItem::count(), 'icon' => 'PackageCheck'],
            ];
            $recentRequests = ToolRequest::with(['vehicle','technician','driver'])->latest()->limit(8)->get();
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'role' => $role,
            'notifications' => Notification::where('user_id', $user->id)->latest()->limit(5)->get(),
        ]);
    }
}
