<?php

namespace App\Tools;

use App\Models\Contact;
use Illuminate\Support\Facades\Validator;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class SaveEmail
{
    public function __construct(private int $chatId) {}

    public function __invoke(?string $email = null, bool $declined = false): string
    {
        $email = $email === null ? null : trim($email);

        if ($email === null || $email === '') {
            if (! $declined) {
                return 'Informe um e-mail ou marque que a pessoa não quis informar.';
            }

            Contact::query()->updateOrCreate(
                ['chat_id' => $this->chatId],
                ['email' => null],
            );

            return 'A pessoa não quis informar o e-mail. Não peça de novo.';
        }

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:255']],
            ['email.email' => 'O e-mail informado não é válido.'],
        );

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            ['email' => $email],
        );

        return 'E-mail salvo.';
    }

    public function tool(): PrismTool
    {
        return Tool::as('saveEmail')
            ->for('Salva o e-mail. Se a pessoa não quiser informar, chame com declined verdadeiro e sem e-mail.')
            ->withStringParameter('email', 'E-mail da pessoa', required: false)
            ->withBooleanParameter('declined', 'Verdadeiro quando a pessoa não quiser informar o e-mail', required: false)
            ->using(fn (?string $email = null, bool $declined = false): string => $this($email, $declined));
    }
}
