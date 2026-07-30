<?php
namespace Database\Seeders;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog=[
            'dashboard'=>['ver'],
            'mapa'=>['ver'],
            'solicitudes'=>['ver','crear','editar'],
            'chat'=>['ver','crear','editar'],
            'notificaciones'=>['ver','editar','gestionar'],
            'inventario'=>['ver','crear','editar','gestionar'],
            'vehiculos'=>['ver','crear','editar','eliminar','estado','recorrido'],
            'proyectos'=>['ver','crear','editar','eliminar','estado'],
            'reservas_vehiculos'=>['ver','crear','editar','gestionar'],
            'reportes'=>['ver','exportar'],
            'usuarios'=>['ver','crear','editar','eliminar'],
            'roles'=>['ver','crear','editar','eliminar'],
            'auditoria'=>['ver'],
            'perfil'=>['ver','editar'],
            'configuracion_gps'=>['ver','crear','editar','eliminar','gestionar'],
        ];
        foreach($catalog as $module=>$actions){ foreach($actions as $action){ Permission::firstOrCreate(['module'=>$module,'action'=>$action], ['name'=>"$module.$action"]); } }
        $superadmin=Role::where('name','Superadministrador')->first(); $superadmin?->permissions()->sync(Permission::pluck('id'));
        $admin=Role::where('name','Administrador')->first(); $admin?->permissions()->sync(Permission::whereIn('module',['dashboard','mapa','solicitudes','chat','notificaciones','inventario','vehiculos','proyectos','reservas_vehiculos','reportes','usuarios','perfil'])->pluck('id'));
        $tech=Role::where('name','Tecnico')->first(); $tech?->permissions()->sync(Permission::whereIn('module',['dashboard','mapa','solicitudes','chat','notificaciones','perfil'])->pluck('id'));
        $driver=Role::where('name','Conductor')->first(); $driver?->permissions()->sync(Permission::whereIn('module',['dashboard','solicitudes','chat','notificaciones','inventario','perfil'])->pluck('id'));
        $this->syncManageableRoleDefaults();
    }

    private function syncManageableRoleDefaults(): void
    {
        if (! Schema::hasTable('role_manageable_roles')) {
            return;
        }

        $roles = Role::query()->get()->keyBy('name');
        $superadmin = $roles->get('Superadministrador');

        if ($superadmin) {
            $superadmin->manageableRoles()->syncWithoutDetaching(
                $roles->reject(fn (Role $role) => $role->name === 'Superadministrador')->pluck('id')->all()
            );
        }

        $this->grantManageableRoles($roles, 'Administrador', ['Administrador1','Administrador2','Administrador','Auditor','Conductor','Tecnico']);
        $this->grantManageableRoles($roles, 'Administrador1', ['Conductor']);
        $this->grantManageableRoles($roles, 'Administrador2', ['Tecnico']);
    }

    private function grantManageableRoles($roles, string $roleName, array $manageableNames): void
    {
        $role = $roles->get($roleName);

        if (! $role) {
            return;
        }

        $role->manageableRoles()->syncWithoutDetaching($roles->only($manageableNames)->pluck('id')->all());
    }
}
