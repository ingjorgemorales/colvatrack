<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model { public $timestamps = false; protected $guarded = []; protected $casts = ['created_at'=>'datetime']; public function vehicle(){ return $this->belongsTo(Vehicle::class); } public function item(){ return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); } public function creator(){ return $this->belongsTo(User::class, 'created_by'); } }
