var token = window.localStorage.getItem('chat_token');
var form = document.querySelector('#chat-form');
var messages = document.querySelector('#messages');
var errorBox = document.querySelector('#chat-error');
var userName = document.querySelector('#user-name');

function loadChats() {
    fetch('/api/chats', {
        headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + token,
        },
    }).then(function (response) {
        return response.json().then(function (body) {
            return { status: response.status, body: body };
        });
    }).then(function (result) {
        if (result.status === 401) {
            window.localStorage.removeItem('chat_token');
            window.location.href = '/login';
            return;
        }

        result.body.chats.forEach(function (chat) {
            chat.messages.forEach(function (message) {
                addMessage(message.message, message.sender === 'user' ? 'user' : 'bot');
            });

            if (chat.status === 'closed') {
                addEnded();
            }
        });
    });
}

if (!token) {
    window.location.href = '/login';
} else {
    loadChats();
}

userName.textContent = window.localStorage.getItem('chat_user_name') || '';

document.querySelector('#logout').addEventListener('click', function () {
    window.localStorage.removeItem('chat_token');
    window.localStorage.removeItem('chat_user_name');
    window.location.href = '/login';
});

form.addEventListener('submit', function (event) {
    var text = form.message.value.trim();

    event.preventDefault();

    if (!text) {
        return;
    }

    errorBox.hidden = true;
    form.querySelector('button').disabled = true;
    addMessage(text, 'user');
    showTyping();
    form.message.value = '';

    fetch('/api/chat', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token,
        },
        body: JSON.stringify({ message: text }),
    }).then(function (response) {
        return response.json().then(function (body) {
            return { ok: response.ok, status: response.status, body: body };
        });
    }).then(function (result) {
        if (result.status === 401) {
            hideTyping();
            window.localStorage.removeItem('chat_token');
            window.location.href = '/login';
            return;
        }

        if (!result.ok) {
            hideTyping();
            errorBox.textContent = result.body.message || 'Não foi possível enviar a mensagem.';
            errorBox.hidden = false;
            return;
        }

        hideTyping();
        addMessage(result.body.message, 'bot');

        if (result.body.status === 'closed') {
            addEnded();
        }
    }).catch(function () {
        hideTyping();
        errorBox.textContent = 'Não foi possível enviar a mensagem.';
        errorBox.hidden = false;
    }).finally(function () {
        form.querySelector('button').disabled = false;
        form.message.focus();
    });
});

function addMessage(text, sender) {
    var item = document.createElement('li');

    item.className = 'message message-' + sender;
    item.textContent = text;
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
}

function showTyping() {
    var item = document.createElement('li');

    item.id = 'typing';
    item.className = 'message message-bot message-typing';
    item.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
}

function addEnded() {
    var item = document.createElement('li');

    item.className = 'conversation-ended';
    item.textContent = 'Conversa encerrada';
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
}

function hideTyping() {
    var item = document.querySelector('#typing');

    if (item) {
        item.remove();
    }
}
