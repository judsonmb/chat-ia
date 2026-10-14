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
use Illuminate\Support\Str;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AIAgentService
{
    /**
     * @var list<string>
     */
    private array $stopWords = [
        'com', 'como', 'das', 'dos', 'ela', 'ele', 'essa', 'esse', 'isso', 'mais',
        'nao', 'para', 'pela', 'pelo', 'por', 'qual', 'que', 'sao', 'seu', 'sua',
        'uma', 'voce', 'voces',
    ];

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

    public function makeRAG(string $message): string
    {
        $terms = $this->terms($message);

        if ($terms === []) {
            return '';
        }

        $match = collect($this->chunks())
            ->map(fn (string $chunk): array => [
                'chunk' => $chunk,
                'score' => count(array_intersect($terms, $this->terms($chunk))),
            ])
            ->filter(fn (array $item): bool => $item['score'] >= 2)
            ->sortByDesc('score')
            ->first();

        if ($match === null) {
            return '';
        }

        return "\n\nTrecho recuperado da base. Use só este texto para explicar o fluxo ou a validação se a pessoa perguntou isso. Não invente regra fora dele. Depois volte para a coleta.\n\n".$match['chunk'];
    }

    /**
     * @return list<string>
     */
    private function chunks(): array
    {
        $chunks = [];

        foreach (File::files(resource_path('knowledge')) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $parts = preg_split('/^## /m', File::get($file->getPathname())) ?: [];

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part !== '' && str_contains($part, "\n")) {
                    $chunks[] = $part;
                }
            }
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function terms(string $text): array
    {
        $normalized = Str::ascii(Str::lower($text));
        preg_match_all('/[a-z0-9]{3,}/', $normalized, $matches);

        return array_values(array_unique(array_diff($matches[0], $this->stopWords)));
    }
}
