<?php
namespace Database\Seeders;
use App\Models\Role;
use Illuminate\Database\Seeder;
class RolesSeeder extends Seeder { public function run(): void { foreach ([['Superadministrador','Acceso total'],['Administrador','Gestion operativa sin roles ni configuracion GPS'],['Tecnico','Gestion de solicitudes y ubicacion'],['Conductor','Gestion de vehiculo, inventario y entregas']] as [$name,$description]) { Role::firstOrCreate(['name'=>$name], ['description'=>$description]); } } }
