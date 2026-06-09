<?php
namespace App\Events;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class UserLocationUpdated implements ShouldBroadcastNow { use SerializesModels; public function __construct(public User $user){} public function broadcastOn(): array { return [new Channel('users.location')]; } }

