<?php

namespace App\Tools;

use App\Enums\ChatStatus;
use App\Models\Chat;
use App\Models\Contact;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

class CloseChat
{
    public function __construct(private int $chatId) {}

    public function __invoke(): string
    {
        $chat = Chat::query()->findOrFail($this->chatId);
        $contact = Contact::query()->where('chat_id', $this->chatId)->first();

        $chat->update([
            'status' => ChatStatus::Closed,
        ]);

        if ($contact === null || $this->hasRequiredData($contact)) {
            return 'Conversa encerrada.';
        }

        $contact->delete();

        return 'Conversa encerrada sem todos os dados obrigatórios. O contato foi excluído.';
    }

    public function tool(): PrismTool
    {
        return Tool::as('closeChat')
            ->for('Encerra a conversa. Se faltar dado obrigatório, exclui o contato.')
            ->using(fn (): string => $this());
    }

    private function hasRequiredData(Contact $contact): bool
    {
        $required = [
            $contact->first_name,
            $contact->last_name,
            $contact->identity_number,
            $contact->phone,
            $contact->zipcode,
            $contact->street,
            $contact->number,
            $contact->neighborhood,
            $contact->city,
            $contact->state,
            $contact->country,
        ];

        foreach ($required as $value) {
            if (blank($value)) {
                return false;
            }
        }

        return true;
    }
}
