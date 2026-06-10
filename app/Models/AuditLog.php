<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime:Y-m-d H:i:s']; public function user(){ return $this->belongsTo(User::class); } }
