<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLocationEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $requiresLocation = $user?->hasRole(...config('colvatrack.location.required_roles', [])) ?? false;
        $maxAge = now()->subMinutes(config('colvatrack.location.max_age_minutes', 10));
        $hasFreshLocation = $user && $user->current_latitude && $user->current_longitude && $user->location_updated_at && $user->location_updated_at->greaterThan($maxAge);

        if ($requiresLocation && !$hasFreshLocation) {
            $message = 'Para usar ColvaTrack debes permitir el acceso a tu ubicacion';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'code' => 'LOCATION_REQUIRED'], 428)
                : redirect()->route('dashboard')->with('error', $message);
        }

        return $next($request);
    }
}
