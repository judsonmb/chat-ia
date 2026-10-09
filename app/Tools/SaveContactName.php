<?php

namespace App\Tools;

use App\Models\Contact;
use Illuminate\Support\Facades\Validator;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class SaveContactName
{
    public function __construct(private int $chatId) {}

    public function __invoke(string $firstName, string $lastName): string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        $validator = Validator::make(
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
            ],
            [
                'firstName' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\p{L}][\p{L}\'\- ]*$/u'],
                'lastName' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\p{L}][\p{L}\'\- ]*$/u'],
            ],
            [
                'firstName.required' => 'Informe o nome.',
                'firstName.min' => 'O nome precisa ter pelo menos 2 letras.',
                'firstName.regex' => 'O nome informado não é válido.',
                'lastName.required' => 'Informe o sobrenome.',
                'lastName.min' => 'O sobrenome precisa ter pelo menos 2 letras.',
                'lastName.regex' => 'O sobrenome informado não é válido.',
            ],
        );

        if ($validator->fails()) {
            return implode(' ', $validator->errors()->all());
        }

        Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
        );

        return 'Nome e sobrenome salvos.';
    }

    public function tool(): PrismTool
    {
        return Tool::as('saveName')
            ->for('Salva o nome e o sobrenome. Nome composto conhecido fica em first_name, como João Paulo ou Maria Eduarda. Todos os sobrenomes ficam em last_name: em Judson Melo Bandeira, first_name é Judson e last_name é Melo Bandeira. Em João Paulo Silva, first_name é João Paulo e last_name é Silva. Não chame se a pessoa ainda não disse o sobrenome.')
            ->withStringParameter('first_name', 'Nome. Pode ser composto, como João Paulo ou Maria Eduarda')
            ->withStringParameter('last_name', 'Sobrenome ou sobrenomes, como Melo Bandeira ou Silva. Partículas de, da e do fazem parte do sobrenome')
            ->using(fn (string $first_name, string $last_name): string => $this($first_name, $last_name));
    }
}
