@extends('layout')

@section('title', 'Chat')

@section('content')
    <div class="chat">
        <header class="chat-header">
            <div>
                <h1>Chat</h1>
                <p id="user-name"></p>
            </div>
            <button id="logout" type="button">Sair</button>
        </header>

        <ol id="messages" class="messages"></ol>

        <form id="chat-form" class="composer">
            <label for="message">Mensagem</label>
            <textarea id="message" name="message" rows="2" required></textarea>
            <button type="submit">Enviar</button>
            <p id="chat-error" class="error" hidden></p>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/chat.js') }}"></script>
@endsection
