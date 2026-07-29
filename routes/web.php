<?php
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordCodeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ChatWebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GpsProviderController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationWebController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ToolRequestWebController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/password-code', [PasswordCodeController::class, 'create'])->name('password.code');
    Route::post('/password-code/verify', [PasswordCodeController::class, 'store'])->name('password.code.verify');
    Route::get('/reset-password', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'must.change.password', 'audit'])->group(function () {
    Route::get('/password/change', [PasswordController::class, 'change'])->name('password.change')->withoutMiddleware('must.change.password');
    Route::post('/password/change', [PasswordController::class, 'update'])->name('password.update')->withoutMiddleware('must.change.password');

    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard,ver');
    Route::get('/mapa', [PageController::class, 'map'])->name('mapa')->middleware('permission:mapa,ver');
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('perfil')->middleware('permission:perfil,ver');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('perfil.update')->middleware('permission:perfil,editar');
    Route::patch('/perfil/password', [ProfileController::class, 'password'])->name('perfil.password')->middleware('permission:perfil,editar');

    Route::get('/inventario', [InventoryController::class, 'index'])->name('inventario.index')->middleware('permission:inventario,ver');
    Route::get('/inventario/catalogo', [InventoryController::class, 'catalog'])->name('inventario.catalogo')->middleware('permission:inventario,gestionar');
    Route::get('/inventario/movimientos', [InventoryController::class, 'movements'])->name('inventario.movimientos')->middleware('permission:inventario,ver');
    Route::post('/inventario/items', [InventoryController::class, 'storeItem'])->name('inventario.items.store')->middleware('permission:inventario,crear');
    Route::patch('/inventario/items/{item}', [InventoryController::class, 'updateItem'])->name('inventario.items.update')->middleware('permission:inventario,editar');
    Route::patch('/inventario/items/{item}/status', [InventoryController::class, 'toggleItemStatus'])->name('inventario.items.status')->middleware('permission:inventario,editar');
    Route::patch('/inventario/stock', [InventoryController::class, 'updateStock'])->name('inventario.stock.update')->middleware('permission:inventario,editar');
    Route::delete('/inventario/stock', [InventoryController::class, 'removeStock'])->name('inventario.stock.remove')->middleware('permission:inventario,editar');

    Route::get('/solicitudes', [ToolRequestWebController::class, 'index'])->name('solicitudes.index')->middleware('permission:solicitudes,ver');
    Route::get('/solicitudes/create', [ToolRequestWebController::class, 'create'])->name('solicitudes.create')->middleware(['permission:solicitudes,crear', 'location.enabled']);
    Route::post('/solicitudes', [ToolRequestWebController::class, 'store'])->name('solicitudes.store')->middleware(['permission:solicitudes,crear', 'location.enabled']);
    Route::get('/solicitudes/{solicitude}', [ToolRequestWebController::class, 'show'])->name('solicitudes.show')->middleware('permission:solicitudes,ver');
    Route::post('/solicitudes/{solicitude}/chat/messages', [ChatWebController::class, 'store'])->name('solicitudes.chat.store')->middleware('permission:chat,crear');
    Route::patch('/solicitudes/{solicitude}/chat/read', [ChatWebController::class, 'read'])->name('solicitudes.chat.read')->middleware('permission:chat,editar');
    Route::patch('/solicitudes/{solicitude}/status', [ToolRequestWebController::class, 'status'])->name('solicitudes.status')->middleware(['permission:solicitudes,editar', 'location.enabled']);

    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index')->middleware('permission:usuarios,ver');
    Route::get('/usuarios/create', [UserController::class, 'create'])->name('usuarios.create')->middleware('permission:usuarios,crear');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store')->middleware('permission:usuarios,crear');
    Route::get('/usuarios/{usuario}/edit', [UserController::class, 'edit'])->name('usuarios.edit')->middleware('permission:usuarios,editar');
    Route::patch('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update')->middleware('permission:usuarios,editar');
    Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update')->middleware('permission:usuarios,editar');
    Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy'])->name('usuarios.destroy')->middleware('permission:usuarios,eliminar');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles,ver');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:roles,crear');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:roles,crear');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:roles,editar');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles,editar');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles,editar');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles,eliminar');

    Route::get('/vehiculos', [VehicleController::class, 'index'])->name('vehiculos.index')->middleware('permission:vehiculos,ver');
    Route::get('/vehiculos/create', [VehicleController::class, 'create'])->name('vehiculos.create')->middleware('permission:vehiculos,crear');
    Route::post('/vehiculos', [VehicleController::class, 'store'])->name('vehiculos.store')->middleware('permission:vehiculos,crear');
    Route::get('/vehiculos/{vehiculo}/edit', [VehicleController::class, 'edit'])->name('vehiculos.edit')->middleware('permission:vehiculos,editar');
    Route::patch('/vehiculos/{vehiculo}', [VehicleController::class, 'update'])->name('vehiculos.update')->middleware('permission:vehiculos,editar');
    Route::put('/vehiculos/{vehiculo}', [VehicleController::class, 'update'])->name('vehiculos.update')->middleware('permission:vehiculos,editar');
    Route::delete('/vehiculos/{vehiculo}', [VehicleController::class, 'destroy'])->name('vehiculos.destroy')->middleware('permission:vehiculos,eliminar');
    Route::get('/vehiculos/{vehiculo}/recorrido', [VehicleController::class, 'routeHistory'])->name('vehiculos.recorrido')->middleware('permission:vehiculos,recorrido');
    Route::get('/configuracion/gps', [GpsProviderController::class, 'index'])->name('gps-providers.index')->middleware('permission:configuracion_gps,ver');
    Route::get('/configuracion/gps/create', [GpsProviderController::class, 'create'])->name('gps-providers.create')->middleware('permission:configuracion_gps,crear');
    Route::post('/configuracion/gps', [GpsProviderController::class, 'store'])->name('gps-providers.store')->middleware('permission:configuracion_gps,crear');
    Route::get('/configuracion/gps/{gpsProvider}/edit', [GpsProviderController::class, 'edit'])->name('gps-providers.edit')->middleware('permission:configuracion_gps,editar');
    Route::patch('/configuracion/gps/{gpsProvider}', [GpsProviderController::class, 'update'])->name('gps-providers.update')->middleware('permission:configuracion_gps,editar');
    Route::put('/configuracion/gps/{gpsProvider}', [GpsProviderController::class, 'update'])->name('gps-providers.update')->middleware('permission:configuracion_gps,editar');
    Route::delete('/configuracion/gps/{gpsProvider}', [GpsProviderController::class, 'destroy'])->name('gps-providers.destroy')->middleware('permission:configuracion_gps,eliminar');
    Route::post('/configuracion/gps/{gpsProvider}/test', [GpsProviderController::class, 'test'])->name('gps-providers.test')->middleware('permission:configuracion_gps,gestionar');


    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index')->middleware('permission:reportes,ver');
    Route::get('/reportes/export', [ReportController::class, 'export'])->name('reportes.export')->middleware('permission:reportes,exportar');
    Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index')->middleware('permission:auditoria,ver');
    Route::get('/notificaciones', [NotificationWebController::class, 'index'])->name('notificaciones.index')->middleware('permission:notificaciones,ver');
    Route::patch('/notificaciones/{notification}/read', [NotificationWebController::class, 'read'])->name('notificaciones.read')->middleware('permission:notificaciones,editar');
    Route::patch('/notificaciones/read-all', [NotificationWebController::class, 'readAll'])->name('notificaciones.read-all')->middleware('permission:notificaciones,editar');
});
