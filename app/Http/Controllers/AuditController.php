<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['module', 'action', 'user_id', 'date_from', 'date_to']);
        $query = AuditLog::with('user')
            ->when($filters['module'] ?? null, fn (Builder $q, $module) => $q->where('module', $module))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $userId) => $q->where('user_id', $userId))
            ->latest('created_at');

        if ($filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return Inertia::render('Audit/Index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name', 'last_name', 'email']),
            'modules' => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
