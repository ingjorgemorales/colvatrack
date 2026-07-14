<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationWebController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 10), 100);
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return Inertia::render('Notifications/Index', [
            'notifications' => Notification::where('user_id', auth()->id())->latest()->paginate($perPage)->withQueryString(),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function read(Notification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->update(['read_at' => now()]);
        return back()->with('success', 'Notificacion marcada como leida.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'Notificaciones marcadas como leidas.');
    }
}
