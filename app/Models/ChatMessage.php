<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatMessage extends Model { protected $guarded = []; protected $casts = ['read_at'=>'datetime:Y-m-d H:i:s','created_at'=>'datetime:Y-m-d H:i:s']; public function chat(){ return $this->belongsTo(Chat::class); } public function sender(){ return $this->belongsTo(User::class, 'sender_id'); } }
