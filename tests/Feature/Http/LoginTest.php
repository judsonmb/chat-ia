<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Passport\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_a_passport_token_and_the_authenticated_user(): void
    {
        Client::factory()->asPersonalAccessTokenClient()->create([
            'name' => 'Test Personal Access Client',
            'provider' => 'users',
        ]);

        $user = User::factory()->create([
            'email' => 'taylor@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'taylor@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPaths([
                'token_type' => 'Bearer',
                'user.id' => $user->id,
                'user.name' => $user->name,
                'user.email' => 'taylor@example.com',
                'token' => fn (mixed $token): bool => is_string($token) && $token !== '',
                'expires_in' => fn (mixed $expiresIn): bool => is_int($expiresIn) && $expiresIn > 0,
            ])
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'name' => 'login',
            'revoked' => false,
        ]);
    }

    public function test_it_returns_422_when_required_login_fields_are_missing(): void
    {
        $this->postJson('/api/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_it_returns_422_when_the_email_is_not_a_valid_email_address(): void
    {
        $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field must be a valid email address.',
            ]);

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    #[DataProvider('invalidCredentials')]
    public function test_it_returns_422_when_the_credentials_are_invalid(string $email, string $password): void
    {
        User::factory()->create([
            'email' => 'taylor@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'These credentials do not match our records.',
            ]);

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_it_returns_429_after_too_many_login_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'taylor@example.com',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'unknown email' => ['unknown@example.com', 'password'],
            'wrong password' => ['taylor@example.com', 'wrong-password'],
        ];
    }
}
