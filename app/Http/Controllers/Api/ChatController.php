<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ToolRequest;
use App\Services\ChatService;
use Illuminate\Http\Request;
class ChatController extends Controller
{
    public function show(Request $request, ToolRequest $toolRequest)
    {
        $this->authorizeRequest($request, $toolRequest);

        return $toolRequest->chat()->with('messages.sender')->firstOrFail();
    }

    public function store(Request $request, ToolRequest $toolRequest, ChatService $service)
    {
        $this->authorizeRequest($request, $toolRequest);

        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);

        return $service->send($toolRequest->chat()->firstOrFail(), $request->user()->id, $data['message']);
    }

    private function authorizeRequest(Request $request, ToolRequest $toolRequest): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('Administrador')
                || (int) $toolRequest->technician_id === (int) $user->id
                || (int) $toolRequest->driver_id === (int) $user->id,
            403
        );
    }
}
