<?php

use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'login'])->name('pages.login');
Route::get('/login', [PagesController::class, 'login']);
Route::get('/register', [PagesController::class, 'register'])->name('pages.register');
Route::get('/chat', [PagesController::class, 'chat'])->name('pages.chat');
