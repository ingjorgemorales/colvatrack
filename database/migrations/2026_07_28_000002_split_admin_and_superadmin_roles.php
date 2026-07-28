<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $superadmin = Role::where('name', 'Superadministrador')->first();
        $legacyAdmin = Role::where('name', 'Administrador')->first();

        if ($legacyAdmin && ! $superadmin) {
            $legacyAdmin->update([
                'name' => 'Superadministrador',
                'description' => 'Acceso total al sistema',
            ]);
            $superadmin = $legacyAdmin;
        }

        $superadmin ??= Role::firstOrCreate(
            ['name' => 'Superadministrador'],
            ['description' => 'Acceso total al sistema']
        );

        $admin = Role::firstOrCreate(
            ['name' => 'Administrador'],
            ['description' => 'Gestion operativa sin roles ni configuracion GPS']
        );

        $superadmin->permissions()->sync(Permission::pluck('id'));

        $adminModules = [
            'dashboard',
            'mapa',
            'solicitudes',
            'chat',
            'notificaciones',
            'inventario',
            'vehiculos',
            'reportes',
            'usuarios',
            'perfil',
        ];

        $admin->permissions()->sync(
            Permission::whereIn('module', $adminModules)->pluck('id')
        );
    }

    public function down(): void
    {
        $superadmin = Role::where('name', 'Superadministrador')->first();
        $admin = Role::where('name', 'Administrador')->first();

        if ($admin) {
            $admin->delete();
        }

        if ($superadmin) {
            $superadmin->update([
                'name' => 'Administrador',
                'description' => 'Acceso total',
            ]);
            $superadmin->permissions()->sync(Permission::pluck('id'));
        }
    }
};
