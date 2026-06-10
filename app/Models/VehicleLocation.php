<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleLocation extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['raw_payload_json'=>'array','gps_datetime'=>'datetime:Y-m-d H:i:s','created_at'=>'datetime:Y-m-d H:i:s']; public function vehicle(){ return $this->belongsTo(Vehicle::class); } }
