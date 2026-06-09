<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ToolRequest;
use App\Services\ChatService;
use Illuminate\Http\Request;
class ChatController extends Controller { public function show(ToolRequest $toolRequest){ return $toolRequest->chat()->with('messages.sender')->firstOrFail(); } public function store(Request $request, ToolRequest $toolRequest, ChatService $service){ $data=$request->validate(['message'=>['required','string','max:3000']]); return $service->send($toolRequest->chat()->firstOrFail(), $request->user()->id, $data['message']); } }
