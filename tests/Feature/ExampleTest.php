<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de fumée : un visiteur non connecté est redirigé vers /login,
 * et la page de login s'affiche correctement.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertStatus(302)->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }
}
