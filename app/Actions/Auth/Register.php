<?php

namespace App\Actions\Auth;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class Register
{
    use AsAction;

    /**
     * @return array{user: User, token: PersonalAccessTokenResult}
     */
    public function handle(string $name, string $email, string $password): array
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken('login'),
        ];
    }
}
