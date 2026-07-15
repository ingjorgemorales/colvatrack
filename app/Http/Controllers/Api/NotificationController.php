<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Notification;
class NotificationController extends Controller {
    public function index(){
        $notifications = Notification::where('user_id',auth()->id())->latest()->paginate(30)
            ->through(fn ($n) => array_merge($n->toArray(), [
                'url' => $n->url(),
            ]));

        return response()->json(array_merge($notifications->toArray(), [
            'unread_count' => Notification::where('user_id', auth()->id())->whereNull('read_at')->count(),
        ]));
    }
    public function read(Notification $notification){ abort_unless($notification->user_id===auth()->id(),403); $notification->update(['read_at'=>now()]); return $notification; }
    public function readAll(){ Notification::where('user_id',auth()->id())->whereNull('read_at')->update(['read_at'=>now()]); return response()->json(['ok'=>true]); }
}
