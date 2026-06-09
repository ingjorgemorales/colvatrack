<?php
namespace App\Events;
use App\Models\ToolRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class ToolRequestStatusChanged implements ShouldBroadcastNow { use SerializesModels; public function __construct(public ToolRequest $toolRequest){} public function broadcastOn(): array { return [new Channel('tool-requests')]; } }

