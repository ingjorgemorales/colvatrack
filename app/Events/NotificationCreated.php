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
            'url' => match ($this->notification->type) {
                'tool_request', 'tool_request_status', 'chat' => $this->notification->data_json ? '/solicitudes/'.$this->notification->data_json['tool_request_id'] : null,
                'gps_stale_summary' => '/mapa',
                'request_delay_summary' => '/solicitudes',
                'low_stock_summary' => '/inventario',
                default => null,
            },
        ]);
    }
}
