<?php
namespace App\Http\Controllers;

use App\Models\ToolRequest;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatWebController extends Controller
{
    public function store(Request $request, ToolRequest $solicitude, ChatService $service)
    {
        $user = $request->user();
        abort_unless($user->hasRole('Administrador') || $solicitude->technician_id === $user->id || $solicitude->driver_id === $user->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $chat = $solicitude->chat()->firstOrFail();
        $service->send($chat, $user->id, $data['message']);
        return back()->with('success', 'Mensaje enviado.');
    }

    public function read(Request $request, ToolRequest $solicitude)
    {
        $user = $request->user();
        abort_unless($user->hasRole('Administrador') || $solicitude->technician_id === $user->id || $solicitude->driver_id === $user->id, 403);
        $solicitude->chat?->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        return back();
    }
}
