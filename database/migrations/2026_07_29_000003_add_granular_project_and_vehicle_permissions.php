<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $permissions = [
        'vehiculos' => ['estado'],
        'proyectos' => ['ver', 'crear', 'editar', 'estado', 'eliminar'],
        'reservas_vehiculos' => ['ver', 'crear', 'editar', 'gestionar'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['name' => $module.'.'.$action]
                );
            }
        }

        Role::where('name', 'Superadministrador')->first()?->permissions()->sync(Permission::pluck('id'));

        Role::with('permissions')->get()->each(function (Role $role) {
            if ($role->name === 'Superadministrador') {
                return;
            }

            $grants = [];

            if ($this->hasPermission($role, 'vehiculos', 'ver')) {
                $grants[] = ['proyectos', 'ver'];
                $grants[] = ['reservas_vehiculos', 'ver'];
            }

            if ($this->hasPermission($role, 'vehiculos', 'crear')) {
                $grants[] = ['proyectos', 'crear'];
            }

            if ($this->hasPermission($role, 'vehiculos', 'editar')) {
                $grants[] = ['vehiculos', 'estado'];
                $grants[] = ['proyectos', 'editar'];
                $grants[] = ['proyectos', 'estado'];
                $grants[] = ['reservas_vehiculos', 'crear'];
                $grants[] = ['reservas_vehiculos', 'editar'];
                $grants[] = ['reservas_vehiculos', 'gestionar'];
            }

            if ($this->hasPermission($role, 'vehiculos', 'eliminar')) {
                $grants[] = ['proyectos', 'eliminar'];
            }

            foreach ($grants as [$module, $action]) {
                $permission = Permission::where('module', $module)->where('action', $action)->first();
                if ($permission && ! $role->permissions()->whereKey($permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        });
    }

    public function down(): void
    {
        foreach ($this->permissions as $module => $actions) {
            Permission::where('module', $module)->whereIn('action', $actions)->delete();
        }
    }

    private function hasPermission(Role $role, string $module, string $action): bool
    {
        return $role->permissions
            ->where('module', $module)
            ->where('action', $action)
            ->isNotEmpty();
    }
};
