<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@example.com',
        ]);

        Passport::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPaths([
                'id' => $user->id,
                'name' => 'Taylor Otwell',
                'email' => 'taylor@example.com',
            ])
            ->assertJsonMissingPath('password');
    }

    public function test_it_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
