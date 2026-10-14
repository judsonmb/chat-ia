<?php

namespace App\Actions\Chat;

use App\Enums\ChatStatus;
use App\Models\Chat;
use App\Models\Contact;
use App\Prompts\ContactCollectorPrompt;
use App\Services\AIAgentService;
use App\Services\PrismService;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMessage
{
    use AsAction;

    public function __construct(
        private PrismService $prismService,
        private AIAgentService $aiAgentService,
        private ContactCollectorPrompt $contactCollectorPrompt,
    ) {}

    /**
     * @return array{message: string, status: string}
     */
    public function handle(string $message, string $sender, int $userId): array
    {
        $chat = Chat::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'status' => ChatStatus::Open,
            ],
        );

        $chat->messages()->create([
            'message' => $message,
            'sender' => $sender,
        ]);

        $messages = $chat->messages()->orderBy('id')->get();
        $contact = Contact::query()->where('chat_id', $chat->id)->first();
        $isFirstReply = $messages->doesntContain('sender', 'bot');

        $reply = $this->prismService->text(
            $this->aiAgentService->makeChatHistory($messages),
            $this->contactCollectorPrompt->text($contact, $isFirstReply).$this->aiAgentService->makeRAG(),
            $this->aiAgentService->makeChatTools($chat->id),
        );

        if ($isFirstReply) {
            $reply = $this->contactCollectorPrompt->greeting()."\n\n".$reply;
        }

        $chat->messages()->create([
            'message' => $reply,
            'sender' => 'bot',
        ]);

        $chat->refresh();

        return [
            'message' => $reply,
            'status' => $chat->status->value,
        ];
    }
}
