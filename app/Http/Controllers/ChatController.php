<?php

namespace App\Http\Controllers;

use App\Actions\Chat\SendMessage;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $chats = $request->user()
            ->chats()
            ->with(['messages' => function ($query): void {
                $query->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        return response()->json([
            'chats' => $chats->map(fn (Chat $chat): array => [
                'id' => $chat->id,
                'status' => $chat->status->value,
                'messages' => $chat->messages->map(fn (ChatMessage $message): array => [
                    'sender' => $message->sender,
                    'message' => $message->message,
                ])->values(),
            ])->values(),
        ]);
    }

    public function sendMessage(SendMessageRequest $request, SendMessage $sendMessage): JsonResponse
    {
        $result = $sendMessage->handle(
            $request->string('message')->toString(),
            'user',
            $request->user()->id,
        );

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
        ], 201);
    }
}
