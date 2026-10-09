<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class Login
{
    use AsAction;

    /**
     * @return array{user: User, token: PersonalAccessTokenResult}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();
        $hashedPassword = $user?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

        if (! $user || ! Hash::check($password, $hashedPassword)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('login'),
        ];
    }
}
