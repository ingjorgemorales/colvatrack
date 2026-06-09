<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleInventory extends Model { protected $table = 'vehicle_inventory'; protected $guarded = []; public function vehicle(){ return $this->belongsTo(Vehicle::class); } public function item(){ return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); } }
