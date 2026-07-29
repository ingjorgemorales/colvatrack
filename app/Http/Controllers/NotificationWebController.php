<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationWebController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canSeeAll = $this->canSeeAllNotifications($user);
        $perPage = min((int) $request->integer('per_page', 10), 100);
        $query = Notification::with('user.role')
            ->when(! $canSeeAll, fn ($q) => $q->where('user_id', $user->id));

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('type', 'like', "%$search%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $readStatus = (string) $request->string('read_status');

        if ($readStatus === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($readStatus === 'unread') {
            $query->whereNull('read_at');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $query->latest()->paginate($perPage)->withQueryString(),
            'types' => Notification::query()
                ->when(! $canSeeAll, fn ($q) => $q->where('user_id', $user->id))
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type'),
            'canSeeAllNotifications' => $canSeeAll,
            'filters' => $request->only(['search', 'type', 'read_status', 'date_from', 'date_to', 'per_page']) + ['per_page' => $perPage],
        ]);
    }

    public function read(Notification $notification)
    {
        $user = auth()->user();
        abort_unless($notification->user_id === $user->id || $this->canSeeAllNotifications($user), 403);
        $notification->update(['read_at' => now()]);
        return back()->with('success', 'Notificacion marcada como leida.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'Notificaciones marcadas como leidas.');
    }

    private function canSeeAllNotifications($user): bool
    {
        return ! $user->hasRole('Tecnico', 'Conductor') && $user->canAccess('notificaciones', 'gestionar');
    }
}
