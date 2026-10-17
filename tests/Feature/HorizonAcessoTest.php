<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_a_permissao_recebe_403(): void
    {
        $this->actingAs($this->usuarioCom('user-list'))
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_visitante_recebe_403(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    public function test_com_a_permissao_abre_o_painel(): void
    {
        $this->actingAs($this->usuarioCom('horizon-acessar'))
            ->get('/horizon')
            ->assertOk();
    }
}
