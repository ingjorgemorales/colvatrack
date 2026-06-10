<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GpsRequestLog extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['requested_at'=>'datetime:Y-m-d H:i:s','created_at'=>'datetime:Y-m-d H:i:s']; public function provider(){ return $this->belongsTo(GpsProvider::class, 'gps_provider_id'); } }
