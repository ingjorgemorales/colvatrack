<?php
namespace App\Events;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class ChatMessageSent implements ShouldBroadcastNow { use SerializesModels; public function __construct(public ChatMessage $message){} public function broadcastOn(): array { return [new Channel('chat.'.$this->message->chat_id)]; } }

