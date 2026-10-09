<?php

namespace App\Tools;

use App\Models\Contact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;
use Throwable;

class LookupZipcode
{
    public function __construct(private int $chatId) {}

    public function __invoke(string $zipcode): string
    {
        $digits = preg_replace('/\D/', '', $zipcode) ?? '';

        if (strlen($digits) !== 8) {
            return 'O CEP informado não é válido. Peça um CEP com 8 dígitos.';
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->retry(2, 100, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                })
                ->get(rtrim((string) config('services.viacep.url'), '/').'/'.$digits.'/json/');
        } catch (Throwable) {
            return 'Não consegui consultar o CEP agora. Peça para a pessoa tentar de novo.';
        }

        if ($response->failed()) {
            return 'O CEP informado não é válido. Peça um CEP com 8 dígitos.';
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (($payload['erro'] ?? false) === true || ($payload['erro'] ?? null) === 'true') {
            return 'Não encontrei esse CEP. Peça outro CEP.';
        }

        $attributes = array_filter([
            'zipcode' => $digits,
            'street' => $this->filled($payload['logradouro'] ?? null),
            'neighborhood' => $this->filled($payload['bairro'] ?? null),
            'city' => $this->filled($payload['localidade'] ?? null),
            'state' => $this->filled($payload['uf'] ?? null),
            'country' => 'Brasil',
        ], fn (?string $value): bool => $value !== null);

        $contact = Contact::query()->updateOrCreate(
            ['chat_id' => $this->chatId],
            $attributes,
        );

        return $this->summary($contact, 'CEP consultado.');
    }

    public function tool(): PrismTool
    {
        return Tool::as('lookupZipcode')
            ->for('Consulta o CEP no ViaCEP e preenche rua, bairro, cidade, estado e país. Não preenche número nem complemento.')
            ->withStringParameter('zipcode', 'CEP com 8 dígitos')
            ->using(fn (string $zipcode): string => $this($zipcode));
    }

    private function summary(Contact $contact, string $lead): string
    {
        $saved = collect([
            'street' => 'rua',
            'neighborhood' => 'bairro',
            'city' => 'cidade',
            'state' => 'estado',
            'country' => 'país',
        ])->filter(fn (string $label, string $column): bool => filled($contact->{$column}))
            ->map(fn (string $label, string $column): string => $label.' '.$contact->{$column})
            ->values();

        $missing = collect([
            'number' => 'número',
            'complement' => 'complemento',
            'neighborhood' => 'bairro',
            'street' => 'rua',
            'city' => 'cidade',
            'state' => 'estado',
        ])->filter(fn (string $label, string $column): bool => blank($contact->{$column}))->values();

        $text = $lead;

        if ($saved->isNotEmpty()) {
            $text .= ' Já salvos, não pergunte de novo: '.$saved->join(', ').'.';
        }

        if ($missing->isEmpty()) {
            return $text.' Não pergunte mais dados de endereço.';
        }

        return $text.' Pergunte somente o que falta: '.$missing->join(', ').'. O complemento é opcional. Se a pessoa não quiser informar, use saveAddress com complement_declined verdadeiro e não invente um valor. Quando a pessoa responder, use saveAddress só com o que ela disse.';
    }

    private function filled(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
