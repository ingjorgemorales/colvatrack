<?php
namespace App\Http\Middleware;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user()?->load('role.permissions');
        $requiresLocation = $user?->hasRole(...config('colvatrack.location.required_roles', [])) ?? false;
        $maxAge = now()->subMinutes(config('colvatrack.location.max_age_minutes', 10));
        $hasFreshLocation = $user && $user->current_latitude && $user->current_longitude && $user->location_updated_at && $user->location_updated_at->greaterThan($maxAge);

        return array_merge(parent::share($request), [
            'auth' => ['user' => $user, 'permissions' => $this->permissions($user)],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'unread_notifications' => fn () => $user ? Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
            'location' => [
                'required' => $requiresLocation,
                'active' => (bool) $hasFreshLocation,
                'updated_at' => $user?->location_updated_at,
                'interval_seconds' => config('colvatrack.location.update_interval_seconds', 60),
                'max_age_minutes' => config('colvatrack.location.max_age_minutes', 10),
                'message' => 'Para usar ColvaTrack debes permitir el acceso a tu ubicacion',
            ],
        ]);
    }

    private function permissions($user): array
    {
        if (! $user || ! $user->role) {
            return [];
        }

        if ($user->hasRole('Administrador')) {
            return ['*'];
        }

        return $user->role->permissions
            ->map(fn ($permission) => $permission->module.'.'.$permission->action)
            ->values()
            ->all();
    }
}

