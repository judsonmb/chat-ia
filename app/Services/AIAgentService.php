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
use Illuminate\Support\Facades\File;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Symfony\Component\Finder\SplFileInfo;

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

    /**
     * Reads every .md file from resources/knowledge (resource_path('knowledge'),
     * e.g. resources/knowledge/fluxo.md and resources/knowledge/validacoes.md)
     * and returns them as the prior knowledge block appended to the system prompt.
     */
    public function makeRAG(): string
    {
        $documents = collect(File::files(resource_path('knowledge')))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md')
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->map(fn (SplFileInfo $file): string => trim(File::get($file->getPathname())))
            ->join("\n\n");

        if ($documents === '') {
            return '';
        }

        return "\n\n=== CONHECIMENTO PRÉVIO ===\n\n".$documents."\n\n=== FIM DO CONHECIMENTO PRÉVIO ===";
    }
}
