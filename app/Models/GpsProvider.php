<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GpsProvider extends Model { protected $guarded = []; protected $casts = ['config_json'=>'array','last_success_at'=>'datetime','last_error_at'=>'datetime']; public function vehicles(){ return $this->hasMany(Vehicle::class); } public function logs(){ return $this->hasMany(GpsRequestLog::class); } }
