<?php

namespace App\Tools;

use App\Models\Contact;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class SavePhone
{
    public function __construct(private int $chatId) {}

    public function __invoke(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 11) {
            return 'O telefone informado não é válido. Peça um telefone com DDD.';
        }

        Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            ['phone' => $digits],
        );

        return 'Telefone salvo.';
    }

    public function tool(): PrismTool
    {
        return Tool::as('savePhone')
            ->for('Salva o telefone com DDD quando a pessoa informar.')
            ->withStringParameter('phone', 'Telefone com DDD')
            ->using(fn (string $phone): string => $this($phone));
    }
}
