<?php
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ToolRequestController;
use App\Http\Controllers\Api\UserLocationController;
use App\Http\Controllers\Api\VehicleLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth'])->group(function () {
    Route::get('/vehicles/locations',[VehicleLocationController::class,'index']);
    Route::post('/users/location',[UserLocationController::class,'store']);
    Route::get('/vehicles/{vehicle}/inventory',[VehicleLocationController::class,'inventory']);
    Route::post('/tool-requests',[ToolRequestController::class,'store'])->middleware('location.enabled');
    Route::patch('/tool-requests/{toolRequest}/status',[ToolRequestController::class,'status'])->middleware('location.enabled');
    Route::get('/tool-requests/{toolRequest}/chat',[ChatController::class,'show']);
    Route::post('/tool-requests/{toolRequest}/chat/messages',[ChatController::class,'store']);
    Route::get('/notifications',[NotificationController::class,'index']);
    Route::patch('/notifications/{notification}/read',[NotificationController::class,'read']);
    Route::patch('/notifications/read-all',[NotificationController::class,'readAll']);
    Route::get('/reports/export',[ReportController::class,'export']);
});
