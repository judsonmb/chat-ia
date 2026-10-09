<?php

namespace Tests\Feature\Http;

use App\Enums\ChatStatus;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ChatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_the_authenticated_users_chats_with_messages(): void
    {
        $user = User::factory()->create();
        $closed = Chat::factory()->for($user)->create([
            'status' => ChatStatus::Closed,
        ]);
        ChatMessage::factory()->for($closed)->create([
            'sender' => 'user',
            'message' => 'Oi',
        ]);
        ChatMessage::factory()->for($closed)->create([
            'sender' => 'bot',
            'message' => 'Olá',
        ]);

        $open = Chat::factory()->for($user)->create([
            'status' => ChatStatus::Open,
        ]);
        ChatMessage::factory()->for($open)->create([
            'sender' => 'user',
            'message' => 'Quero continuar',
        ]);

        $other = User::factory()->create();
        $foreign = Chat::factory()->for($other)->create();
        ChatMessage::factory()->for($foreign)->create([
            'message' => 'Mensagem de outra pessoa',
        ]);

        Passport::actingAs($user);

        $this->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(2, 'chats')
            ->assertJsonPath('chats.0.id', $closed->id)
            ->assertJsonPath('chats.0.status', 'closed')
            ->assertJsonPath('chats.0.messages.0.message', 'Oi')
            ->assertJsonPath('chats.0.messages.1.message', 'Olá')
            ->assertJsonPath('chats.1.id', $open->id)
            ->assertJsonPath('chats.1.status', 'open')
            ->assertJsonPath('chats.1.messages.0.message', 'Quero continuar')
            ->assertJsonMissing([
                'message' => 'Mensagem de outra pessoa',
            ]);
    }

    public function test_it_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/chats')->assertUnauthorized();
    }
}
