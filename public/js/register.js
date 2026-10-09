var form = document.querySelector('#register-form');
var errorBox = document.querySelector('#register-error');

if (window.localStorage.getItem('chat_token')) {
    window.location.href = '/chat';
}

form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.hidden = true;
    form.querySelector('button').disabled = true;

    fetch('/api/register', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: form.name.value,
            email: form.email.value,
            password: form.password.value,
            password_confirmation: form.password_confirmation.value,
        }),
    }).then(function (response) {
        return response.json().then(function (body) {
            return { ok: response.ok, body: body };
        });
    }).then(function (result) {
        if (result.ok) {
            window.localStorage.setItem('chat_token', result.body.token);
            window.localStorage.setItem('chat_user_name', result.body.user.name);
            window.location.href = '/chat';
            return;
        }

        errorBox.textContent = errorMessage(result.body);
        errorBox.hidden = false;
    }).catch(function () {
        errorBox.textContent = 'Não foi possível criar a conta. Tente de novo.';
        errorBox.hidden = false;
    }).finally(function () {
        form.querySelector('button').disabled = false;
    });
});

function errorMessage(body) {
    if (body.errors) {
        var messages = [];

        Object.keys(body.errors).forEach(function (field) {
            messages = messages.concat(body.errors[field]);
        });

        return messages.join(' ');
    }

    return body.message || 'Não foi possível criar a conta.';
}
