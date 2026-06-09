<?php
namespace App\Events;
use App\Models\Vehicle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class InventoryUpdated implements ShouldBroadcastNow { use SerializesModels; public function __construct(public Vehicle $vehicle){} public function broadcastOn(): array { return [new Channel('inventory.'.$this->vehicle->id)]; } }

