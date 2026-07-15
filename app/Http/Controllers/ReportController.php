<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        return Inertia::render('Reports/Index', [
            'reports' => $reports->catalog(),
            'filters' => $request->only(['type', 'date_from', 'date_to', 'vehicle_id', 'user_id', 'category_id', 'status', 'read_status']),
            'vehicles' => Vehicle::orderBy('plate')->get(['id', 'plate']),
            'users' => User::with('role')->orderBy('name')->get(['id', 'role_id', 'name', 'last_name', 'email']),
            'categories' => InventoryCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, ReportService $reports)
    {
        $type = $request->query('type', 'vehicles');
        $filters = $request->only(['date_from', 'date_to', 'vehicle_id', 'user_id', 'category_id', 'status', 'read_status']);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'export',
            'module' => 'reportes',
            'description' => 'Exportacion XLSX: '.$type,
            'new_values' => $filters,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $reports->download($type, $filters);
    }
}
