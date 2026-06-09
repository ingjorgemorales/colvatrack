<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ToolRequestStatusHistory extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['created_at'=>'datetime:Y-m-d H:i:s']; public function request(){ return $this->belongsTo(ToolRequest::class, 'tool_request_id'); } public function user(){ return $this->belongsTo(User::class, 'changed_by'); } }
