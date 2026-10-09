@extends('layout')

@section('title', 'Criar conta')

@section('content')
    <main class="login">
        <form id="register-form" class="card">
            <h1>Criar conta</h1>
            <p class="lead">Informe seu nome, e-mail e uma senha.</p>

            <label for="name">Nome</label>
            <input id="name" name="name" type="text" autocomplete="name" required>

            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" required>

            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <p id="register-error" class="error" hidden></p>

            <button type="submit">Cadastrar</button>

            <p class="switch">Já tem conta? <a href="{{ route('pages.login') }}">Entrar</a></p>
        </form>
    </main>
@endsection

@section('scripts')
    <script src="{{ asset('js/register.js') }}"></script>
@endsection
