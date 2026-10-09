<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_the_user_and_returns_a_passport_token(): void
    {
        Client::factory()->asPersonalAccessTokenClient()->create([
            'name' => 'Test Personal Access Client',
            'provider' => 'users',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'judson@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'judson@example.com')->first();

        $response
            ->assertCreated()
            ->assertJsonPaths([
                'token_type' => 'Bearer',
                'user.id' => $user->id,
                'user.name' => 'Judson',
                'user.email' => 'judson@example.com',
                'token' => fn (mixed $token): bool => is_string($token) && $token !== '',
                'expires_in' => fn (mixed $expiresIn): bool => is_int($expiresIn) && $expiresIn > 0,
            ])
            ->assertJsonMissingPath('user.password');

        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'name' => 'login',
            'revoked' => false,
        ]);
    }

    public function test_it_returns_422_when_required_register_fields_are_missing(): void
    {
        $this->postJson('/api/register')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_returns_422_when_the_email_is_not_a_valid_email_address(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field must be a valid email address.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_returns_422_when_the_email_is_already_used(): void
    {
        User::factory()->create([
            'email' => 'judson@example.com',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'judson@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email has already been taken.',
            ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_returns_422_when_the_password_confirmation_does_not_match(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'judson@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'The password field confirmation does not match.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_returns_422_when_the_password_is_too_short(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'judson@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'The password field must be at least 8 characters.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_returns_429_after_too_many_register_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/register', [
                'name' => 'Judson',
                'email' => "judson{$attempt}@example.com",
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/register', [
            'name' => 'Judson',
            'email' => 'judson6@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('users', 0);
    }
}
