<?php
namespace Database\Seeders;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
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
    }
}
