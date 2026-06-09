<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\LocationService;
use Illuminate\Http\Request;
class UserLocationController extends Controller { public function store(Request $request, LocationService $service){ $data=$request->validate(['latitude'=>['required','numeric'],'longitude'=>['required','numeric'],'accuracy'=>['nullable','numeric']]); return $service->update($request->user(), $data['latitude'], $data['longitude'], $data['accuracy'] ?? null); } }
