<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register')
    ->name('register');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::get('/chats', [ChatController::class, 'index'])
        ->name('chats.index');

    Route::post('/chat', [ChatController::class, 'sendMessage'])
        ->name('chat.sendMessage');
});
