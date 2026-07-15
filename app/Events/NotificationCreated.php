<?php
namespace App\Events;
use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class NotificationCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('notifications.'.$this->notification->user_id)];
    }

    public function broadcastWith(): array
    {
        return ['notification' => $this->payload()];
    }

    private function payload(): array
    {
        return array_merge($this->notification->toArray(), [
            'url' => $this->notification->url(),
        ]);
    }
}
