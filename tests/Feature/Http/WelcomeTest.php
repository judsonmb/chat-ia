<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class WelcomeTest extends TestCase
{
    public function test_it_shows_the_login_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('Criar conta')
            ->assertDontSee('Log in');
    }
}
