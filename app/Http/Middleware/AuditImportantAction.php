<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Vehicle;
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
            'description' => $this->description($request),
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

    private function description(Request $request): string
    {
        $route = (string) $request->route()?->getName();
        $user = $this->actor($request);

        return match ($route) {
            'password.update', 'perfil.password' => $user.' cambio su contrasena.',
            'perfil.update' => $user.' actualizo los datos de su perfil.',
            'inventario.items.store' => $user.' registro la herramienta '.$this->payloadName($request).'.',
            'inventario.items.update' => $user.' actualizo la herramienta '.$this->modelName($request, 'item').'.',
            'inventario.items.status' => $user.' '.($this->routeModelStatus($request, 'item') === 'active' ? 'activo' : 'desactivo').' la herramienta '.$this->modelName($request, 'item').'.',
            'inventario.stock.update' => $this->stockDescription($request, $user),
            'solicitudes.store' => $this->requestStoreDescription($request, $user),
            'solicitudes.status' => $user.' cambio la solicitud #'.$this->modelKey($request, 'solicitude').' al estado '.$request->input('status').'.',
            'solicitudes.chat.store' => $user.' envio un mensaje en la solicitud #'.$this->modelKey($request, 'solicitude').'.',
            'usuarios.store' => $user.' creo el usuario '.$this->payloadPerson($request).'.',
            'usuarios.update' => $user.' actualizo el usuario '.$this->modelPerson($request, 'usuario').'.',
            'usuarios.destroy' => $user.' desactivo el usuario '.$this->modelPerson($request, 'usuario').'.',
            'vehiculos.store' => $user.' creo el vehiculo '.$request->input('plate', 'sin placa').'.',
            'vehiculos.update' => $user.' actualizo el vehiculo '.$this->modelName($request, 'vehiculo', 'plate').'.',
            'vehiculos.destroy' => $user.' desactivo el vehiculo '.$this->modelName($request, 'vehiculo', 'plate').'.',
            'roles.store' => $user.' creo el rol '.$this->payloadName($request).'.',
            'roles.update' => $user.' actualizo el rol '.$this->modelName($request, 'role').'.',
            'roles.destroy' => $user.' elimino el rol '.$this->modelName($request, 'role').'.',
            'gps-providers.store' => $user.' creo el proveedor GPS '.$this->payloadName($request).'.',
            'gps-providers.update' => $user.' actualizo el proveedor GPS '.$this->modelName($request, 'gpsProvider').'.',
            'gps-providers.destroy' => $user.' desactivo el proveedor GPS '.$this->modelName($request, 'gpsProvider').'.',
            'gps-providers.test' => $user.' probo la conexion del proveedor GPS '.$this->modelName($request, 'gpsProvider').'.',
            default => $user.' realizo una accion en '.$this->module($request).': '.$request->method().' /'.$request->path().'.',
        };
    }

    private function actor(Request $request): string
    {
        $user = $request->user();

        return trim(($user?->name ?? 'Sistema').' '.($user?->last_name ?? '')) ?: 'Sistema';
    }

    private function routeModel(Request $request, string $key): mixed
    {
        return $request->route()?->parameter($key);
    }

    private function modelKey(Request $request, string $key): string
    {
        $model = $this->routeModel($request, $key);

        if (is_object($model) && method_exists($model, 'getKey')) {
            return (string) $model->getKey();
        }

        return (string) ($model ?: '-');
    }

    private function modelName(Request $request, string $key, string $field = 'name'): string
    {
        $model = $this->routeModel($request, $key);

        if (is_object($model)) {
            return (string) ($model->{$field} ?? $model->name ?? ('#'.$model->getKey()));
        }

        return (string) ($model ?: '-');
    }

    private function modelPerson(Request $request, string $key): string
    {
        $model = $this->routeModel($request, $key);

        if (is_object($model)) {
            $name = trim(($model->name ?? '').' '.($model->last_name ?? ''));

            return $name ?: (string) ($model->email ?? '#'.$model->getKey());
        }

        return (string) ($model ?: '-');
    }

    private function routeModelStatus(Request $request, string $key): ?string
    {
        $model = $this->routeModel($request, $key);

        return is_object($model) ? ($model->status ?? null) : null;
    }

    private function payloadName(Request $request): string
    {
        return (string) ($request->input('name') ?? $request->input('category_name') ?? 'sin nombre');
    }

    private function payloadPerson(Request $request): string
    {
        $name = trim((string) $request->input('name').' '.(string) $request->input('last_name'));

        return $name ?: (string) $request->input('email', 'sin nombre');
    }

    private function stockDescription(Request $request, string $user): string
    {
        $vehicle = Vehicle::find($request->input('vehicle_id'));
        $item = InventoryItem::find($request->input('inventory_item_id'));
        $total = $request->input('quantity_total', 0);
        $available = $request->input('quantity_available', $total);

        return $user.' actualizo el inventario del vehiculo '.($vehicle?->plate ?? '#'.$request->input('vehicle_id')).': '
            .($item?->name ?? 'herramienta #'.$request->input('inventory_item_id')).' con total '.$total.' y disponible '.$available.'.';
    }

    private function requestStoreDescription(Request $request, string $user): string
    {
        $vehicle = Vehicle::find($request->input('vehicle_id'));
        $items = collect($request->input('items', []))->count();

        return $user.' creo una solicitud de herramientas para el vehiculo '.($vehicle?->plate ?? '#'.$request->input('vehicle_id')).' con '.$items.' item(s).';
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
