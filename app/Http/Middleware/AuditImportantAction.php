<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditImportantAction
{
    private array $skipRoutes = [
        'notificaciones.read',
        'notificaciones.read-all',
        'solicitudes.chat.read',
    ];

    private array $sensitiveKeys = [
        '_token', '_method', 'password', 'password_confirmation', 'current_password', 'token',
        'api_key', 'api_key_encrypted', 'secret', 'mail_pass', 'mail_password', 'message',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldAudit($request, $response)) {
            return $response;
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => strtolower($request->method()),
            'module' => $this->module($request),
            'description' => trim($request->method().' /'.$request->path().' '.($request->route()?->getName() ? '('.$request->route()?->getName().')' : '')),
            'old_values' => ['route_parameters' => $this->routeParameters($request)],
            'new_values' => $this->cleanPayload($request->all()),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'created_at' => now(),
        ]);

        return $response;
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return ! in_array((string) $request->route()?->getName(), $this->skipRoutes, true);
    }

    private function module(Request $request): string
    {
        $segment = $request->segment(1) ?: 'sistema';

        return match ($segment) {
            'password', 'login', 'logout' => 'seguridad',
            'configuracion' => 'configuracion',
            default => str_replace('-', '_', $segment),
        };
    }

    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(function ($value) {
                if (is_object($value)) {
                    return method_exists($value, 'getKey') ? class_basename($value).':'.$value->getKey() : class_basename($value);
                }

                return $value;
            })
            ->all();
    }

    private function cleanPayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $clean[$key] = '[filtrado]';
                continue;
            }

            $clean[$key] = is_array($value) ? $this->cleanPayload($value) : $value;
        }

        return $clean;
    }
}
