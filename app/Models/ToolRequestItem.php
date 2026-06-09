<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ToolRequestItem extends Model { protected $guarded = []; public function request(){ return $this->belongsTo(ToolRequest::class, 'tool_request_id'); } public function item(){ return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); } }
