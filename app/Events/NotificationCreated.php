<?php
namespace App\Events;
use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class NotificationCreated implements ShouldBroadcastNow { use SerializesModels; public function __construct(public Notification $notification){} public function broadcastOn(): array { return [new Channel('notifications.'.$this->notification->user_id)]; } }

