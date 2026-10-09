<?php

namespace App\Tools;

use App\Models\Contact;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class SaveCpf
{
    public function __construct(private int $chatId) {}

    public function __invoke(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf) ?? '';

        if (! $this->isValid($digits)) {
            return 'O CPF informado não é válido. Peça outro CPF. Pontos e traços são aceitos.';
        }

        Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            ['identity_number' => $digits],
        );

        return 'CPF salvo.';
    }

    public function tool(): PrismTool
    {
        return Tool::as('saveCpf')
            ->for('Valida e salva o CPF. Aceita 11 dígitos com ou sem pontos e traços, como 529.982.247-25 ou 52998224725.')
            ->withStringParameter('cpf', 'CPF como a pessoa escreveu, com ou sem pontos e traços')
            ->using(fn (string $cpf): string => $this($cpf));
    }

    private function isValid(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($length = 9; $length < 11; $length++) {
            $sum = 0;

            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $cpf[$index] * (($length + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
