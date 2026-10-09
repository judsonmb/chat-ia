<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Tools\CloseChat;
use App\Tools\LookupZipcode;
use App\Tools\SaveAddress;
use App\Tools\SaveContactName;
use App\Tools\SaveCpf;
use App\Tools\SaveEmail;
use App\Tools\SavePhone;
use Illuminate\Database\Eloquent\Collection;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AIAgentService
{
    /**
     * @param  Collection<int, ChatMessage>  $messages
     * @return array<int, UserMessage|AssistantMessage|SystemMessage>
     */
    public function makeChatHistory(Collection $messages): array
    {
        return $messages->map(fn (ChatMessage $message): UserMessage|AssistantMessage|SystemMessage => match ($message->sender) {
            'user' => new UserMessage($message->message),
            'bot' => new AssistantMessage($message->message),
            'system' => new SystemMessage($message->message),
        })->all();
    }

    /**
     * @return array<int, Tool>
     */
    public function makeChatTools(int $chatId): array
    {
        return [
            (new SaveContactName($chatId))->tool(),
            (new SaveCpf($chatId))->tool(),
            (new LookupZipcode($chatId))->tool(),
            (new SaveAddress($chatId))->tool(),
            (new SaveEmail($chatId))->tool(),
            (new SavePhone($chatId))->tool(),
            (new CloseChat($chatId))->tool(),
        ];
    }
}
