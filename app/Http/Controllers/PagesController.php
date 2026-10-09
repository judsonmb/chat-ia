<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PagesController extends Controller
{
    public function login(): View
    {
        return view('login');
    }

    public function register(): View
    {
        return view('register');
    }

    public function chat(): View
    {
        return view('chat');
    }
}
