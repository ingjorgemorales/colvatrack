<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryItem extends Model { protected $guarded = []; public function category(){ return $this->belongsTo(InventoryCategory::class, 'inventory_category_id'); } public function vehicleInventories(){ return $this->hasMany(VehicleInventory::class); } }
