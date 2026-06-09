<?php
namespace Database\Seeders;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class AdminUserSeeder extends Seeder { public function run(): void { $role=Role::where('name','Administrador')->first(); User::updateOrCreate(['email'=>'admin@colvatrack.com'], ['role_id'=>$role?->id,'name'=>'Administrador','last_name'=>'ColvaTrack','phone'=>'3000000000','cargo'=>'Administrador','password'=>Hash::make('Admin123*'),'status'=>'active','must_change_password'=>true]); } }
