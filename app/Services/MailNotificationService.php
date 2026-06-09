<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailNotificationService
{
    public function sendPlain(?string $to, string $subject, string $body): void
    {
        if (!$to) { return; }
        try {
            Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
        } catch (\Throwable $e) {
            Log::warning('No fue posible enviar correo ColvaTrack', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
