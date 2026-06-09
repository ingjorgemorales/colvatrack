<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Chat extends Model { protected $guarded = []; public function request(){ return $this->belongsTo(ToolRequest::class, 'tool_request_id'); } public function messages(){ return $this->hasMany(ChatMessage::class); } }
