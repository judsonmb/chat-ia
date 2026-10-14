<?php

namespace Tests\Feature\Http;

use App\Enums\ChatStatus;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\User;
use App\Prompts\ContactCollectorPrompt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_the_prism_reply_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Hello from Prism')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Hello from the user',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Hello from Prism')
            ->assertJsonPath('status', 'open');

        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'status' => ChatStatus::Open,
        ]);

        $chatId = $user->chats()->value('id');

        $this->assertDatabaseHas('chat_messages', [
            'chat_id' => $chatId,
            'message' => 'Hello from the user',
            'sender' => 'user',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_id' => $chatId,
            'message' => 'Hello from Prism',
            'sender' => 'bot',
        ]);

        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseCount('chat_messages', 2);

        $fake->assertRequest(function (array $requests): void {
            $systemPrompts = $requests[0]->systemPrompts();

            $this->assertCount(1, $systemPrompts);
            $this->assertInstanceOf(SystemMessage::class, $systemPrompts[0]);
            $this->assertSame(app(ContactCollectorPrompt::class)->text(), $systemPrompts[0]->content);

            $messages = $requests[0]->messages();

            $this->assertCount(1, $messages);
            $this->assertInstanceOf(UserMessage::class, $messages[0]);
            $this->assertSame('Hello from the user', $messages[0]->content);

            $tools = $requests[0]->tools();

            $this->assertEqualsCanonicalizing(
                ['saveName', 'saveCpf', 'lookupZipcode', 'saveAddress', 'saveEmail', 'savePhone', 'closeChat'],
                collect($tools)->map(fn ($tool) => $tool->name())->all(),
            );
        });
    }

    public function test_it_adds_the_matching_knowledge_excerpt_to_the_system_prompt(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('O CPF usa dois dígitos verificadores.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Como funciona a validação do CPF?',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $prompt = $requests[0]->systemPrompts()[0]->content;

            $this->assertStringContainsString('dígitos verificadores', $prompt);
            $this->assertStringNotContainsString('viacep.com.br', $prompt);
        });
    }

    public function test_it_returns_closed_when_the_chat_is_closed_during_the_turn(): void
    {
        $user = User::factory()->create();
        Prism::fake([
            TextResponseFake::make()
                ->withText('A conversa foi encerrada.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Chat::created(function (Chat $chat): void {
            $chat->update([
                'status' => ChatStatus::Closed,
            ]);
        });

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Pode encerrar',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'A conversa foi encerrada.')
            ->assertJsonPath('status', 'closed');
    }

    public function test_it_sends_the_existing_chat_history_before_the_new_message(): void
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create([
            'status' => ChatStatus::Open,
        ]);

        ChatMessage::factory()->for($chat)->create([
            'message' => 'Qual a capital da Alemanha?',
            'sender' => 'user',
        ]);

        ChatMessage::factory()->for($chat)->create([
            'message' => 'A capital da Alemanha é Berlim.',
            'sender' => 'bot',
        ]);

        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Cerca de 3,7 milhões.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'E qual a população de lá?',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Cerca de 3,7 milhões.');

        $fake->assertRequest(function (array $requests): void {
            $messages = $requests[0]->messages();

            $this->assertCount(3, $messages);
            $this->assertInstanceOf(UserMessage::class, $messages[0]);
            $this->assertSame('Qual a capital da Alemanha?', $messages[0]->content);
            $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
            $this->assertSame('A capital da Alemanha é Berlim.', $messages[1]->content);
            $this->assertInstanceOf(UserMessage::class, $messages[2]);
            $this->assertSame('E qual a população de lá?', $messages[2]->content);
        });
    }

    public function test_it_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/chat', [
            'message' => 'Hello',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('chats', 0);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_it_returns_422_when_the_message_is_missing(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/chat')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'message' => 'The message field is required.',
            ]);

        $this->assertDatabaseCount('chats', 0);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_it_does_not_persist_a_client_supplied_sender(): void
    {
        $user = User::factory()->create();

        Prism::fake([
            TextResponseFake::make()
                ->withText('Hello from Prism')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Hello',
            'sender' => 'system',
            'user_id' => null,
        ])->assertCreated();

        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'status' => ChatStatus::Open,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'message' => 'Hello',
            'sender' => 'user',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'message' => 'Hello from Prism',
            'sender' => 'bot',
        ]);
    }

    public function test_it_saves_a_valid_name_through_the_prism_tool(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Nome salvo.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Judson Silva',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $saved = $requests[0]->tools()[0]->handle(first_name: 'Judson', last_name: 'Silva');

            $this->assertSame('Nome e sobrenome salvos.', $saved);

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'first_name' => 'Judson',
                'last_name' => 'Silva',
            ]);
        });
    }

    public function test_it_does_not_save_an_invalid_name_through_the_prism_tool(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Pode repetir o nome?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '123',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $result = $requests[0]->tools()[0]->handle(first_name: '123', last_name: 'Silva');

            $this->assertSame('O nome informado não é válido.', $result);
        });

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_it_fills_the_address_from_viacep_and_asks_for_the_missing_fields(): void
    {
        Http::fake([
            'viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => '',
                'bairro' => '',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
        ]);

        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Qual o número, o complemento e o bairro?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '01001-000',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $lookup = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'lookupZipcode');

            $result = $lookup->handle(zipcode: '01001-000');

            $this->assertSame(
                'CEP consultado. Já salvos, não pergunte de novo: rua Praça da Sé, cidade São Paulo, estado SP, país Brasil. Pergunte somente o que falta: número, complemento, bairro. O complemento é opcional. Se a pessoa não quiser informar, use saveAddress com complement_declined verdadeiro e não invente um valor. Quando a pessoa responder, use saveAddress só com o que ela disse.',
                $result,
            );

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'zipcode' => '01001000',
                'street' => 'Praça da Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'country' => 'Brasil',
                'neighborhood' => null,
                'complement' => null,
                'number' => null,
            ]);

            $saved = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveAddress')
                ->handle(number: '100', complement: 'apto 12', neighborhood: 'Sé');

            $this->assertStringStartsWith('Campos de endereço salvos.', $saved);
            $this->assertStringContainsString('cidade São Paulo', $saved);
            $this->assertStringContainsString('não pergunte de novo', $saved);
        });

        $this->assertDatabaseHas('contacts', [
            'chat_id' => $chatId,
            'number' => '100',
            'complement' => 'apto 12',
            'neighborhood' => 'Sé',
            'street' => 'Praça da Sé',
        ]);
    }

    public function test_it_does_not_ask_for_a_neighborhood_returned_by_viacep(): void
    {
        Http::fake([
            'viacep.com.br/ws/01001000/json/' => Http::response([
                'logradouro' => 'Praça da Sé',
                'complemento' => 'até 2014/2015',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
        ]);

        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Qual o número e o complemento?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '01001000',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $lookup = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'lookupZipcode');

            $this->assertSame(
                'CEP consultado. Já salvos, não pergunte de novo: rua Praça da Sé, bairro Sé, cidade São Paulo, estado SP, país Brasil. Pergunte somente o que falta: número, complemento. O complemento é opcional. Se a pessoa não quiser informar, use saveAddress com complement_declined verdadeiro e não invente um valor. Quando a pessoa responder, use saveAddress só com o que ela disse.',
                $lookup->handle(zipcode: '01001000'),
            );

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'neighborhood' => 'Sé',
                'number' => null,
                'complement' => null,
            ]);
        });
    }

    public function test_it_does_not_save_an_unknown_zipcode(): void
    {
        Http::fake([
            'viacep.com.br/ws/00000000/json/' => Http::response([
                'erro' => true,
            ]),
        ]);

        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Não encontrei esse CEP.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '00000-000',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $lookup = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'lookupZipcode');

            $this->assertSame(
                'Não encontrei esse CEP. Peça outro CEP.',
                $lookup->handle(zipcode: '00000-000'),
            );
        });

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_it_saves_a_valid_email_through_the_prism_tool(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('E-mail salvo.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'judson@example.com',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveEmail');

            $this->assertSame('E-mail salvo.', $tool->handle(email: 'judson@example.com'));

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'email' => 'judson@example.com',
            ]);
        });
    }

    public function test_it_does_not_save_an_invalid_email(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Pode repetir o e-mail?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'nao-e-email',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveEmail');

            $this->assertSame('O e-mail informado não é válido.', $tool->handle(email: 'nao-e-email'));
        });

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_it_accepts_when_the_person_does_not_want_to_give_an_email(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Tudo bem, seguimos sem e-mail.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Não quero informar o e-mail',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveEmail');

            $this->assertSame(
                'A pessoa não quis informar o e-mail. Não peça de novo.',
                $tool->handle(declined: true),
            );

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'email' => null,
            ]);
        });
    }

    public function test_it_saves_a_phone_with_area_code(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Telefone salvo.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '(11) 99999-8888',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'savePhone');

            $this->assertSame('Telefone salvo.', $tool->handle(phone: '(11) 99999-8888'));

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'phone' => '11999998888',
            ]);
        });
    }

    public function test_it_does_not_save_a_phone_without_enough_digits(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Pode repetir o telefone com DDD?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '9999',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'savePhone');

            $this->assertSame(
                'O telefone informado não é válido. Peça um telefone com DDD.',
                $tool->handle(phone: '9999'),
            );
        });

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_it_soft_deletes_the_contact_when_the_chat_closes_before_the_required_data(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Conversa encerrada.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Quero parar',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        Contact::factory()->create([
            'chat_id' => $chatId,
            'first_name' => 'Judson',
            'last_name' => 'Silva',
            'phone' => null,
        ]);

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'closeChat');

            $this->assertSame(
                'Conversa encerrada sem todos os dados obrigatórios. O contato foi excluído.',
                $tool->handle(),
            );

            $this->assertSoftDeleted('contacts', [
                'chat_id' => $chatId,
            ]);

            $this->assertDatabaseHas('chats', [
                'id' => $chatId,
                'status' => ChatStatus::Closed,
            ]);
        });
    }

    public function test_it_keeps_the_contact_when_the_chat_closes_with_the_required_data(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Conversa encerrada.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'Pode encerrar',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        Contact::factory()->create([
            'chat_id' => $chatId,
            'first_name' => 'Judson',
            'last_name' => 'Silva',
            'identity_number' => '52998224725',
            'phone' => '11999998888',
            'zipcode' => '01001000',
            'street' => 'Praça da Sé',
            'number' => '100',
            'complement' => null,
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'Brasil',
            'email' => null,
        ]);

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'closeChat');

            $this->assertSame('Conversa encerrada.', $tool->handle());

            $this->assertNotSoftDeleted('contacts', [
                'chat_id' => $chatId,
                'first_name' => 'Judson',
            ]);

            $this->assertDatabaseHas('chats', [
                'id' => $chatId,
                'status' => ChatStatus::Closed,
            ]);
        });
    }

    public function test_it_saves_a_valid_cpf(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('CPF salvo.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '529.982.247-25',
        ])->assertCreated();

        $chatId = $user->chats()->value('id');

        $fake->assertRequest(function (array $requests) use ($chatId): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveCpf');

            $this->assertSame('CPF salvo.', $tool->handle(cpf: '529.982.247-25'));
            $this->assertSame('CPF salvo.', $tool->handle(cpf: '52998224725'));

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chatId,
                'identity_number' => '52998224725',
            ]);
        });
    }

    public function test_it_does_not_save_an_invalid_cpf(): void
    {
        $user = User::factory()->create();
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Pode repetir o CPF?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => '062200904',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveCpf');

            $this->assertSame(
                'O CPF informado não é válido. Peça outro CPF. Pontos e traços são aceitos.',
                $tool->handle(cpf: '062200904'),
            );
        });

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_it_does_not_save_a_complement_when_the_person_declines(): void
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        Contact::factory()->for($chat)->create([
            'street' => 'Praça da Sé',
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'Brasil',
            'complement' => null,
        ]);

        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Sem problema.')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'não quero informar',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests) use ($chat): void {
            $tool = collect($requests[0]->tools())->first(fn ($tool) => $tool->name() === 'saveAddress');

            $result = $tool->handle(number: '81', complement: 'apto 12', complement_declined: true);

            $this->assertStringContainsString('não invente um valor.', $result);
            $this->assertStringContainsString('cidade São Paulo', $result);
            $this->assertStringContainsString('bairro Sé', $result);

            $this->assertDatabaseHas('contacts', [
                'chat_id' => $chat->id,
                'number' => '81',
                'complement' => null,
                'city' => 'São Paulo',
                'neighborhood' => 'Sé',
            ]);
        });
    }

    public function test_it_tells_the_model_which_address_fields_are_already_saved(): void
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        Contact::factory()->for($chat)->create([
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
        ]);

        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Qual o número?')
                ->withUsage(new Usage(10, 20)),
        ]);

        Passport::actingAs($user);

        $this->postJson('/api/chat', [
            'message' => 'oi',
        ])->assertCreated();

        $fake->assertRequest(function (array $requests): void {
            $prompt = $requests[0]->systemPrompts()[0]->content;

            $this->assertStringContainsString('Dados já salvos. Não peça de novo:', $prompt);
            $this->assertStringContainsString('bairro: Sé', $prompt);
            $this->assertStringContainsString('cidade: São Paulo', $prompt);
        });
    }
}
