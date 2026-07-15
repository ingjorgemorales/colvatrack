<?php
namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\Chat;

class ChatService
{
    public function __construct(private NotificationService $notifications, private MailNotificationService $mail) {}

    public function send(Chat $chat, int $senderId, string $message)
    {
        $msg = $chat->messages()->create(['sender_id' => $senderId, 'message' => $message]);
        $msg->load('sender');
        $chat->loadMissing('request.technician', 'request.driver');
        $recipient = $chat->technician_id === $senderId ? $chat->request->driver : $chat->request->technician;
        try { broadcast(new ChatMessageSent($msg))->toOthers(); } catch (\Throwable $e) { /* WebSocket no disponible */ }

        if ($recipient) {
            $this->notifications->create($recipient->id, 'Nuevo mensaje de chat', 'Tienes un nuevo mensaje en la solicitud #'.$chat->tool_request_id, 'chat', ['tool_request_id' => $chat->tool_request_id, 'chat_id' => $chat->id]);

            app()->terminating(function () use ($recipient, $chat) {
                $this->mail->sendPlain($recipient->email, 'Nuevo mensaje en ColvaTrack', 'Tienes un nuevo mensaje en la solicitud #'.$chat->tool_request_id.'. Ingresa a ColvaTrack para responder.');
            });
        }

        return $msg;
    }
}
