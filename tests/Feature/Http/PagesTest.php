<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_it_shows_the_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('E-mail')
            ->assertSee('Senha')
            ->assertSee('Criar conta');
    }

    public function test_it_shows_the_register_page(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Criar conta')
            ->assertSee('Nome')
            ->assertSee('Confirmar senha')
            ->assertSee('Cadastrar');
    }

    public function test_it_shows_the_chat_page(): void
    {
        $this->get('/chat')
            ->assertOk()
            ->assertSee('Mensagem')
            ->assertSee('Enviar')
            ->assertSee('Sair');
    }
}
