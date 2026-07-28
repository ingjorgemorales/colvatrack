<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['module' => 'mapa', 'action' => 'ver'],
            ['name' => 'mapa.ver']
        );

        $technician = Role::where('name', 'Tecnico')->first();
        if ($technician && ! $technician->permissions()->whereKey($permission->id)->exists()) {
            $technician->permissions()->attach($permission->id);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('module', 'mapa')->where('action', 'ver')->first();
        $technician = Role::where('name', 'Tecnico')->first();

        if ($permission && $technician) {
            $technician->permissions()->detach($permission->id);
        }
    }
};
