<?php
namespace Database\Seeders;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Database\Seeder;
class DemoInventorySeeder extends Seeder { public function run(): void { $cat=InventoryCategory::firstOrCreate(['name'=>'Herramientas de red'], ['description'=>'Equipos para instalacion y soporte','status'=>'active']); $items=[['Ponchadora','unidad'],['Escalera fibra','unidad'],['Medidor de potencia','unidad'],['Conectores RG6','unidad']]; foreach($items as [$name,$unit]){ $item=InventoryItem::firstOrCreate(['name'=>$name], ['inventory_category_id'=>$cat->id,'unit'=>$unit,'status'=>'active']); foreach(Vehicle::all() as $vehicle){ VehicleInventory::updateOrCreate(['vehicle_id'=>$vehicle->id,'inventory_item_id'=>$item->id], ['quantity_total'=>10,'quantity_available'=>8,'quantity_reserved'=>1,'quantity_delivered'=>1,'status'=>'active']); } } } }
