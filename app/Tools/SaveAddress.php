<?php

namespace App\Tools;

use App\Models\Contact;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class SaveAddress
{
    public function __construct(private int $chatId) {}

    public function __invoke(
        ?string $number = null,
        ?string $complement = null,
        ?string $neighborhood = null,
        ?string $street = null,
        ?string $city = null,
        ?string $state = null,
        ?string $country = null,
        bool $complementDeclined = false,
    ): string {
        $attributes = array_filter([
            'number' => $this->filled($number),
            'complement' => $complementDeclined ? null : $this->filled($complement),
            'neighborhood' => $this->filled($neighborhood),
            'street' => $this->filled($street),
            'city' => $this->filled($city),
            'state' => $this->filled($state),
            'country' => $this->filled($country),
        ], fn (?string $value): bool => $value !== null);

        if ($complementDeclined) {
            $attributes['complement'] = null;
        }

        if ($attributes === []) {
            return 'Nenhum campo de endereço foi informado.';
        }

        $contact = Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            $attributes,
        );

        if ($complementDeclined) {
            return 'A pessoa não quis informar o complemento. Não peça de novo e não invente um valor. '.$this->alreadySaved($contact);
        }

        return 'Campos de endereço salvos. '.$this->alreadySaved($contact);
    }

    public function tool(): PrismTool
    {
        return Tool::as('saveAddress')
            ->for('Salva só os campos de endereço que a pessoa informou. Não invente complemento, bairro ou cidade. Se ela não quiser o complemento, chame com complement_declined verdadeiro e sem complement.')
            ->withStringParameter('number', 'Número do endereço', required: false)
            ->withStringParameter('complement', 'Complemento dito pela pessoa', required: false)
            ->withStringParameter('neighborhood', 'Bairro', required: false)
            ->withStringParameter('street', 'Rua', required: false)
            ->withStringParameter('city', 'Cidade', required: false)
            ->withStringParameter('state', 'Estado', required: false)
            ->withStringParameter('country', 'País', required: false)
            ->withBooleanParameter('complement_declined', 'Verdadeiro quando a pessoa não quiser informar o complemento', required: false)
            ->using(fn (
                ?string $number = null,
                ?string $complement = null,
                ?string $neighborhood = null,
                ?string $street = null,
                ?string $city = null,
                ?string $state = null,
                ?string $country = null,
                bool $complement_declined = false,
            ): string => $this($number, $complement, $neighborhood, $street, $city, $state, $country, $complement_declined));
    }

    private function alreadySaved(Contact $contact): string
    {
        $saved = collect([
            'street' => 'rua',
            'neighborhood' => 'bairro',
            'city' => 'cidade',
            'state' => 'estado',
            'country' => 'país',
            'number' => 'número',
        ])->filter(fn (string $label, string $column): bool => filled($contact->{$column}))
            ->map(fn (string $label, string $column): string => $label.' '.$contact->{$column})
            ->values();

        if ($saved->isEmpty()) {
            return 'Não pergunte campos que já estão salvos.';
        }

        return 'Já salvos, não pergunte de novo: '.$saved->join(', ').'.';
    }

    private function filled(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
