<?php
namespace App\Http\Controllers;

use App\Models\GpsProvider;
use App\Models\GpsRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class GpsProviderController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $providers = GpsProvider::withCount('vehicles')
            ->latest()
            ->paginate(10)
            ->through(function (GpsProvider $provider) use ($today) {
                $lastLog = $provider->logs()->latest('id')->first(['id', 'status', 'response_code', 'response_count', 'requested_at', 'created_at', 'error_message']);
                $todayLogs = GpsRequestLog::where('gps_provider_id', $provider->id)
                    ->whereDate('created_at', $today)
                    ->selectRaw('count(*) as total, sum(case when status = "success" then 1 else 0 end) as success_total, sum(case when status <> "success" then 1 else 0 end) as error_total')
                    ->first();

                return array_merge($provider->toArray(), [
                    'vehicles_count' => $provider->vehicles_count,
                    'daily_estimate' => (int) ceil(86400 / max((int) $provider->request_interval_seconds, 1)),
                    'recommended_interval_seconds' => (int) ceil(86400 / max((int) $provider->daily_limit, 1)),
                    'last_log' => $lastLog,
                    'today_requests' => (int) ($todayLogs->total ?? 0),
                    'today_success_requests' => (int) ($todayLogs->success_total ?? 0),
                    'today_error_requests' => (int) ($todayLogs->error_total ?? 0),
                    'alert_config' => $this->alertConfig($provider),
                ]);
            });

        return Inertia::render('GpsProviders/Index', ['providers' => $providers]);
    }

    public function create()
    {
        return Inertia::render('GpsProviders/Form', ['provider' => null, 'defaults' => $this->defaultConfig()]);
    }

    public function store(Request $request)
    {
        GpsProvider::create($this->validated($request));
        return redirect()->route('gps-providers.index')->with('success', 'Proveedor GPS creado.');
    }

    public function edit(GpsProvider $gpsProvider)
    {
        return Inertia::render('GpsProviders/Form', ['provider' => $gpsProvider, 'defaults' => $this->defaultConfig()]);
    }

    public function update(Request $request, GpsProvider $gpsProvider)
    {
        $gpsProvider->update($this->validated($request, $gpsProvider));
        return redirect()->route('gps-providers.index')->with('success', 'Proveedor GPS actualizado.');
    }

    public function destroy(GpsProvider $gpsProvider)
    {
        $gpsProvider->update(['status' => 'inactive']);
        return back()->with('success', 'Proveedor GPS desactivado.');
    }

    public function test(Request $request, GpsProvider $gpsProvider)
    {
        $data = $request->validate(['moviles' => ['required', 'string', 'max:500']]);
        try {
            $response = Http::withHeaders(['x-api-key' => $gpsProvider->api_key_encrypted])
                ->timeout(15)
                ->get($gpsProvider->base_url, [
                    'accion' => data_get($gpsProvider->config_json, 'accion', 'lastposition'),
                    'cliente' => $gpsProvider->client_code,
                    'moviles' => str_replace(' ', '', $data['moviles']),
                ]);

            $gpsProvider->update([
                'last_success_at' => $response->successful() ? now() : $gpsProvider->last_success_at,
                'last_error_at' => $response->successful() ? null : now(),
                'last_error_message' => $response->successful() ? null : $response->body(),
            ]);

            return back()->with($response->successful() ? 'success' : 'error', $response->successful() ? 'Conexion GPS exitosa.' : 'La API respondio con error: '.$response->status());
        } catch (\Throwable $e) {
            $gpsProvider->update(['last_error_at' => now(), 'last_error_message' => $e->getMessage()]);
            return back()->with('error', 'No fue posible conectar: '.$e->getMessage());
        }
    }

    private function validated(Request $request, ?GpsProvider $provider = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:120'],
            'api_key_encrypted' => ['nullable', 'string'],
            'request_interval_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', 'in:active,inactive'],
            'config_json' => ['nullable'],
        ]);

        if ($provider && blank($data['api_key_encrypted'] ?? null)) {
            unset($data['api_key_encrypted']);
        }

        if (is_string($data['config_json'] ?? null)) {
            $data['config_json'] = json_decode($data['config_json'], true) ?: null;
        }

        $data['config_json'] = array_replace_recursive($this->defaultConfig(), $data['config_json'] ?? []);

        return $data;
    }

    private function defaultConfig(): array
    {
        return [
            'header' => 'x-api-key',
            'accion' => 'lastposition',
            'moviles' => config('colvatrack.gps.moviles'),
            'alerts' => [
                'enabled' => true,
                'email_enabled' => false,
                'gps_stale_after_minutes' => 15,
                'request_pending_alert_minutes' => 30,
                'request_en_route_alert_minutes' => 60,
                'inventory_low_stock_threshold' => 1,
                'repeat_minutes' => 60,
            ],
        ];
    }

    private function alertConfig(GpsProvider $provider): array
    {
        return array_replace($this->defaultConfig()['alerts'], (array) data_get($provider->config_json, 'alerts', []));
    }
}