<?php

namespace App\Services;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class PrismService
{
    /**
     * @param  array<int, UserMessage|AssistantMessage|SystemMessage>  $messages
     * @param  array<int, Tool>  $tools
     */
    public function text(array $messages, string $systemPrompt, array $tools = []): string
    {
        $response = Prism::text()
            ->using(Provider::OpenAI, config('services.openai.model'))
            ->withSystemPrompt($systemPrompt)
            ->withMessages($messages)
            ->withTools($tools)
            ->withMaxSteps(3)
            ->asText();

        return $response->text;
    }
}
