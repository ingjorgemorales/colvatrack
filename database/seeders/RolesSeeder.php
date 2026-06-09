<?php
namespace Database\Seeders;
use App\Models\Role;
use Illuminate\Database\Seeder;
class RolesSeeder extends Seeder { public function run(): void { foreach ([['Administrador','Acceso total'],['Tecnico','Gestion de solicitudes y ubicacion'],['Conductor','Gestion de vehiculo, inventario y entregas']] as [$name,$description]) { Role::firstOrCreate(['name'=>$name], ['description'=>$description]); } } }
