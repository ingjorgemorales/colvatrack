<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GpsRequestLog extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['requested_at'=>'datetime','created_at'=>'datetime']; public function provider(){ return $this->belongsTo(GpsProvider::class, 'gps_provider_id'); } }
