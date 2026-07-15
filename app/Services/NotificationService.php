<?php
namespace App\Services;
use App\Events\NotificationCreated;
use App\Models\Notification;
class NotificationService
{
    public function __construct(private FcmPushService $push) {}

    public function create(int $userId, string $title, string $message, string $type='info', array $data=[]): Notification
    {
        $notification = Notification::create(['user_id'=>$userId,'title'=>$title,'message'=>$message,'type'=>$type,'data_json'=>$data ?: null]);
        try { broadcast(new NotificationCreated($notification))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }
        $this->push->sendToUser($notification);
        return $notification;
    }
}
