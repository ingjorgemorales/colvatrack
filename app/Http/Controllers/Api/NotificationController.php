<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Notification;
class NotificationController extends Controller {
    public function index(){
        return Notification::where('user_id',auth()->id())->latest()->paginate(30)
            ->through(fn ($n) => array_merge($n->toArray(), [
                'url' => match ($n->type) {
                    'tool_request', 'tool_request_status', 'chat' => $n->data_json ? '/solicitudes/'.$n->data_json['tool_request_id'] : null,
                    'gps_stale_summary' => '/mapa',
                    'request_delay_summary' => '/solicitudes',
                    'low_stock_summary' => '/inventario',
                    default => null,
                },
            ]));
    }
    public function read(Notification $notification){ abort_unless($notification->user_id===auth()->id(),403); $notification->update(['read_at'=>now()]); return $notification; }
    public function readAll(){ Notification::where('user_id',auth()->id())->whereNull('read_at')->update(['read_at'=>now()]); return response()->json(['ok'=>true]); }
}
