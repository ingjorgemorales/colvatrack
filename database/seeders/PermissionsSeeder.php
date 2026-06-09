<?php
namespace Database\Seeders;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules=['dashboard','mapa','solicitudes','chat','notificaciones','inventario','vehiculos','reportes','usuarios','roles','permisos','auditoria','perfil','configuracion_gps'];
        $actions=['ver','crear','editar','eliminar','exportar','gestionar','recorrido'];
        foreach($modules as $module){ foreach($actions as $action){ Permission::firstOrCreate(['module'=>$module,'action'=>$action], ['name'=>"$module.$action"]); } }
        $admin=Role::where('name','Administrador')->first(); $admin?->permissions()->sync(Permission::pluck('id'));
        $tech=Role::where('name','Tecnico')->first(); $tech?->permissions()->sync(Permission::whereIn('module',['dashboard','mapa','solicitudes','chat','notificaciones','perfil'])->pluck('id'));
        $driver=Role::where('name','Conductor')->first(); $driver?->permissions()->sync(Permission::whereIn('module',['dashboard','solicitudes','chat','notificaciones','inventario','perfil'])->pluck('id'));
    }
}
