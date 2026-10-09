<?php

namespace App\Http\Controllers;

use App\Actions\Auth\Login;
use App\Actions\Auth\Register;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, Login $login): JsonResponse
    {
        $result = $login->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['token']->accessToken,
            'token_type' => $result['token']->tokenType,
            'expires_in' => $result['token']->expiresIn,
            'user' => $result['user'],
        ]);
    }

    public function register(RegisterRequest $request, Register $register): JsonResponse
    {
        $result = $register->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['token']->accessToken,
            'token_type' => $result['token']->tokenType,
            'expires_in' => $result['token']->expiresIn,
            'user' => $result['user'],
        ], 201);
    }
}
