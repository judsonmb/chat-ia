@extends('layout')

@section('title', 'Entrar')

@section('content')
    <main class="login">
        <form id="login-form" class="card">
            <h1>Entrar</h1>
            <p class="lead">Use o e-mail e a senha da sua conta.</p>

            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" required>

            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <p id="login-error" class="error" hidden></p>

            <button type="submit">Entrar</button>

            <p class="switch">Não tem conta? <a href="{{ route('pages.register') }}">Criar conta</a></p>
        </form>
    </main>
@endsection

@section('scripts')
    <script src="{{ asset('js/login.js') }}"></script>
@endsection
