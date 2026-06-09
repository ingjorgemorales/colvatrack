<?php
namespace Database\Seeders;
use App\Models\GpsProvider;
use Illuminate\Database\Seeder;
class GpsProviderSeeder extends Seeder { public function run(): void { GpsProvider::updateOrCreate(['name'=>'ServiceTrack Triplog'], ['base_url'=>config('colvatrack.gps.base_url'),'client_code'=>config('colvatrack.gps.client'),'api_key_encrypted'=>config('colvatrack.gps.api_key'),'request_interval_seconds'=>config('colvatrack.gps.interval_seconds'),'daily_limit'=>config('colvatrack.gps.daily_limit'),'status'=>'active','config_json'=>['header'=>'x-api-key','accion'=>'lastposition']]); } }
