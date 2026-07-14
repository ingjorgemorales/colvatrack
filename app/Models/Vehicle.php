<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model { protected $guarded = []; protected $casts = ['last_gps_datetime'=>'datetime:Y-m-d H:i:s','current_latitude'=>'decimal:7','current_longitude'=>'decimal:7']; public function provider(){ return $this->belongsTo(GpsProvider::class, 'gps_provider_id'); } public function driver(){ return $this->belongsTo(User::class, 'driver_id'); } public function locations(){ return $this->hasMany(VehicleLocation::class); } public function inventory(){ return $this->hasMany(VehicleInventory::class); } public function toolRequests(){ return $this->hasMany(ToolRequest::class); } public function activeToolRequest(){ return $this->hasOne(ToolRequest::class)->activeForVehicle()->latestOfMany(); } }
