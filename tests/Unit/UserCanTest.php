<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserCanTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissao_por_nome(): void
    {
        $user = $this->usuarioCom('user-list');

        $this->assertTrue($user->can('user-list'));
        $this->assertFalse($user->can('user-delete'));
    }

    public function test_array_exige_ao_menos_uma_e_com_true_exige_todas(): void
    {
        $user = $this->usuarioCom('user-list');

        $this->assertTrue($user->can(['user-list', 'user-delete']));
        $this->assertFalse($user->can(['user-list', 'user-delete'], true));
        $this->assertTrue($this->usuarioCom('user-list', 'user-delete')->can(['user-list', 'user-delete'], true));
    }

    public function test_ability_do_gate_com_model_vai_para_o_gate(): void
    {
        Gate::define('editar-proprio', fn (User $user, User $alvo) => $user->is($alvo));
        [$user, $outro] = [$this->usuarioCom(), User::factory()->create()];

        $this->assertTrue($user->can('editar-proprio', $user));
        $this->assertFalse($user->can('editar-proprio', $outro));
    }

    public function test_ability_do_gate_sem_argumentos_vai_para_o_gate(): void
    {
        Gate::define('sempre', fn (User $user) => true);

        $this->assertTrue($this->usuarioCom()->can('sempre'));
    }
}
