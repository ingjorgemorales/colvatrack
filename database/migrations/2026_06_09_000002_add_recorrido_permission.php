<?php
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(
            ['module' => 'vehiculos', 'action' => 'recorrido'],
            ['name' => 'vehiculos.recorrido']
        );

        $admin = Role::where('name', 'Administrador')->first();
        if ($admin && !$admin->permissions()->where('permission_id', $perm->id)->exists()) {
            $admin->permissions()->attach($perm->id);
        }

        $driver = Role::where('name', 'Conductor')->first();
        if ($driver && $driver->permissions()->where('module', 'vehiculos')->where('action', 'ver')->exists()) {
            $driver->permissions()->attach($perm->id);
        }

        $tech = Role::where('name', 'Tecnico')->first();
        if ($tech && $tech->permissions()->where('module', 'vehiculos')->where('action', 'ver')->exists()) {
            $tech->permissions()->attach($perm->id);
        }
    }

    public function down(): void
    {
        Permission::where('module', 'vehiculos')->where('action', 'recorrido')->delete();
    }
};
