<?php
namespace Database\Seeders;
use App\Models\GpsProvider;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DemoVehiclesSeeder extends Seeder
{
    public function run(): void
    {
        $driverRole=Role::where('name','Conductor')->first();
        $driver=User::updateOrCreate(['email'=>'conductor@colvatrack.com'], ['role_id'=>$driverRole?->id,'name'=>'Carlos','last_name'=>'Conductor','phone'=>'3105550001','cargo'=>'Conductor','password'=>Hash::make('Admin123*'),'status'=>'active','must_change_password'=>true,'current_latitude'=>4.6482,'current_longitude'=>-74.0950,'location_updated_at'=>now()]);
        $techRole=Role::where('name','Tecnico')->first();
        User::updateOrCreate(['email'=>'tecnico@colvatrack.com'], ['role_id'=>$techRole?->id,'name'=>'Tatiana','last_name'=>'Tecnico','phone'=>'3205550002','cargo'=>'Tecnico','password'=>Hash::make('Admin123*'),'status'=>'active','must_change_password'=>true,'current_latitude'=>4.6410,'current_longitude'=>-74.0820,'location_updated_at'=>now()]);
        $provider=GpsProvider::first();
        $vehicles=[['WPQ084','Chevrolet','NHR',2021,'Blanco',4.647223,-74.0898436666667,0,'Transmision normal (Apagado)'],['ZGA89H','Renault','Kangoo',2022,'Blanco',4.6660,-74.0580,24,'Transmision normal'],['JTY407','Chevrolet','Dmax',2020,'Gris',4.6215,-74.1102,0,'Detenido']];
        foreach($vehicles as [$plate,$brand,$model,$year,$color,$lat,$lng,$speed,$event]){ Vehicle::updateOrCreate(['plate'=>$plate], ['brand'=>$brand,'model'=>$model,'year'=>$year,'color'=>$color,'status'=>'active','gps_provider_id'=>$provider?->id,'external_gps_id'=>$plate,'driver_id'=>$driver->id,'current_latitude'=>$lat,'current_longitude'=>$lng,'current_speed'=>$speed,'current_heading'=>314,'current_address'=>'Bogota D.C, Colombia','last_gps_datetime'=>now(),'last_gps_event'=>$event,'imei'=>'865209070927067','odometer'=>0,'gps_status'=>'ok','gps_device_brand'=>'BOXTRACK','gps_device_model'=>'G 06L','battery'=>'0','gps_marker_url'=>'https://demo.gservicetrack.com/trackingvip/images_cliente/vehiculo.png']); }
    }
}
