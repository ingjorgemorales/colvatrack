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
    Route::get('/inventario/catalogo', [InventoryController::class, 'catalog'])->name('inventario.catalogo')->middleware('role:Administrador');
    Route::get('/inventario/movimientos', [InventoryController::class, 'movements'])->name('inventario.movimientos')->middleware('permission:inventario,ver');
    Route::post('/inventario/items', [InventoryController::class, 'storeItem'])->name('inventario.items.store')->middleware('role:Administrador');
    Route::patch('/inventario/items/{item}', [InventoryController::class, 'updateItem'])->name('inventario.items.update')->middleware('role:Administrador');
    Route::patch('/inventario/items/{item}/status', [InventoryController::class, 'toggleItemStatus'])->name('inventario.items.status')->middleware('role:Administrador');
    Route::patch('/inventario/stock', [InventoryController::class, 'updateStock'])->name('inventario.stock.update')->middleware('permission:inventario,editar');

    Route::get('/solicitudes', [ToolRequestWebController::class, 'index'])->name('solicitudes.index')->middleware('permission:solicitudes,ver');
    Route::get('/solicitudes/create', [ToolRequestWebController::class, 'create'])->name('solicitudes.create')->middleware(['permission:solicitudes,crear', 'location.enabled']);
    Route::post('/solicitudes', [ToolRequestWebController::class, 'store'])->name('solicitudes.store')->middleware(['permission:solicitudes,crear', 'location.enabled']);
    Route::get('/solicitudes/{solicitude}', [ToolRequestWebController::class, 'show'])->name('solicitudes.show')->middleware('permission:solicitudes,ver');
    Route::post('/solicitudes/{solicitude}/chat/messages', [ChatWebController::class, 'store'])->name('solicitudes.chat.store')->middleware('permission:chat,crear');
    Route::patch('/solicitudes/{solicitude}/chat/read', [ChatWebController::class, 'read'])->name('solicitudes.chat.read')->middleware('permission:chat,editar');
    Route::patch('/solicitudes/{solicitude}/status', [ToolRequestWebController::class, 'status'])->name('solicitudes.status')->middleware(['permission:solicitudes,editar', 'location.enabled']);

    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'usuario'])->except('show')->middleware('role:Administrador');
    Route::resource('roles', RoleController::class)->except('show')->middleware('role:Administrador');
    Route::get('/vehiculos/actividad', [VehicleController::class, 'activity'])->name('vehiculos.actividad')->middleware('role:Administrador');
    Route::resource('vehiculos', VehicleController::class)->parameters(['vehiculos' => 'vehiculo'])->except('show')->middleware('role:Administrador');
    Route::get('/vehiculos/{vehiculo}/recorrido', [VehicleController::class, 'routeHistory'])->name('vehiculos.recorrido')->middleware('permission:vehiculos,recorrido');
    Route::resource('configuracion/gps', GpsProviderController::class)->parameters(['gps' => 'gpsProvider'])->names('gps-providers')->except('show')->middleware('role:Administrador');
    Route::post('/configuracion/gps/{gpsProvider}/test', [GpsProviderController::class, 'test'])->name('gps-providers.test')->middleware('role:Administrador');


    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index')->middleware('permission:reportes,ver');
    Route::get('/reportes/export', [ReportController::class, 'export'])->name('reportes.export')->middleware('permission:reportes,exportar');
    Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index')->middleware(['role:Administrador', 'permission:auditoria,ver']);
    Route::get('/notificaciones', [NotificationWebController::class, 'index'])->name('notificaciones.index')->middleware('permission:notificaciones,ver');
    Route::patch('/notificaciones/{notification}/read', [NotificationWebController::class, 'read'])->name('notificaciones.read')->middleware('permission:notificaciones,editar');
    Route::patch('/notificaciones/read-all', [NotificationWebController::class, 'readAll'])->name('notificaciones.read-all')->middleware('permission:notificaciones,editar');
});
