<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function export(Request $request, ReportService $service)
    {
        return $service->download($request->query('type', 'vehicles'), $request->only(['date_from', 'date_to', 'vehicle_id', 'user_id', 'category_id', 'status']));
    }
}
