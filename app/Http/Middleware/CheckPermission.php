<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class CheckPermission { public function handle(Request $request, Closure $next, string $module, string $action = 'ver'){ abort_unless($request->user()?->canAccess($module, $action), 403); return $next($request); } }
