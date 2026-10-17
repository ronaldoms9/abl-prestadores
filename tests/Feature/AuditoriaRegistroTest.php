<?php

namespace Tests\Feature;

use App\Models\User;
use App\Permission;
use App\Role;
use App\Support\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_e_falha_geram_uma_linha_cada(): void
    {
        $user = User::factory()->create();
        Activity::query()->delete();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'senha-errada-123']);

        $this->assertSame(['login', 'logout', 'login-falhou'], Activity::orderBy('id')->pluck('event')->all());

        [$login, $logout, $falha] = Activity::with('causer')->orderBy('id')->get();
        $this->assertTrue($login->causer->is($user));
        $this->assertTrue($logout->causer->is($user));
        $this->assertNull($falha->causer);
        $this->assertSame($user->email, $falha->properties['email']);
        $this->assertSame('127.0.0.1', $falha->properties['ip']);
        $this->assertStringNotContainsString('senha-errada-123', $falha->properties->toJson());
    }

    public function test_editar_o_nome_grava_antes_e_depois_e_salvar_sem_mudanca_nao_grava(): void
    {
        $admin = $this->usuarioCom('user-edit');
        $alvo = User::factory()->create(['name' => 'Maria']);
        $perfil = Role::create(['name' => 'rh', 'display_name' => 'RH']);
        $alvo->roles()->attach($perfil);
        Activity::query()->delete();

        $dados = ['nome' => 'Maria Souza', 'email' => $alvo->email, 'grupo_acesso' => $perfil->id];
        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), $dados)->assertOk();
        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), $dados)->assertOk();

        $registro = Activity::with(['causer', 'subject'])->sole();
        $this->assertSame('usuario-alterado', $registro->event);
        $this->assertTrue($registro->causer->is($admin));
        $this->assertTrue($registro->subject->is($alvo));
        $this->assertSame(['name' => 'Maria'], $registro->properties['old']);
        $this->assertSame(['name' => 'Maria Souza'], $registro->properties['attributes']);
    }

    public function test_trocar_a_senha_registra_o_fato_sem_o_hash(): void
    {
        $admin = $this->usuarioCom('user-edit');
        $alvo = User::factory()->create();
        $perfil = Role::create(['name' => 'rh', 'display_name' => 'RH']);
        $alvo->roles()->attach($perfil);
        Activity::query()->delete();

        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), [
            'nome' => $alvo->name, 'email' => $alvo->email, 'grupo_acesso' => $perfil->id, 'senha' => 'novaSenha123',
        ])->assertOk();

        $registro = Activity::with('subject')->sole();
        $this->assertSame('usuario-senha-alterada', $registro->event);
        $this->assertTrue($registro->subject->is($alvo));
        $json = $registro->properties->toJson();
        $this->assertStringNotContainsString('novaSenha123', $json);
        $this->assertStringNotContainsString($alvo->fresh()->password, $json);
        $this->assertStringNotContainsString('password', $json);
    }

    public function test_troca_de_senha_no_perfil_e_registrada(): void
    {
        $user = User::factory()->create(['password' => Hash::make('atual123')]);
        Activity::query()->delete();

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password'), [
            'old_password' => 'atual123', 'password' => 'abc12345', 'password_confirmation' => 'abc12345',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['usuario-senha-alterada'], Activity::pluck('event')->all());
    }

    public function test_mudar_o_perfil_do_usuario_registra_antes_e_depois(): void
    {
        $admin = $this->usuarioCom('user-edit');
        $alvo = User::factory()->create();
        $rh = Role::create(['name' => 'rh', 'display_name' => 'RH']);
        $gestor = Role::create(['name' => 'gestor', 'display_name' => 'Gestor']);
        $alvo->roles()->attach($rh);
        Activity::query()->delete();

        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), [
            'nome' => $alvo->name, 'email' => $alvo->email, 'grupo_acesso' => $gestor->id,
        ])->assertOk();

        $registro = Activity::sole();
        $this->assertSame('usuario-perfil-alterado', $registro->event);
        $this->assertSame(['RH'], $registro->properties['antes']);
        $this->assertSame(['Gestor'], $registro->properties['depois']);
    }

    public function test_alterar_as_permissoes_do_perfil_registra_incluidas_e_removidas(): void
    {
        $admin = $this->usuarioCom('grupo-edit');
        $perfil = Role::create(['name' => 'rh', 'display_name' => 'RH', 'description' => 'Recursos humanos']);
        $manter = Permission::create(['name' => 'pessoa-list', 'display_name' => 'Ver pessoas']);
        $remover = Permission::create(['name' => 'empresa-list', 'display_name' => 'Ver empresas']);
        $incluir = Permission::create(['name' => 'obra-list', 'display_name' => 'Ver obras']);
        $perfil->permissions()->attach([$manter->id, $remover->id]);
        Activity::query()->delete();

        $this->actingAs($admin)->postJson(route('grupo.store', $perfil->id), [
            'nome_grupo' => 'RH', 'descricao' => 'Recursos humanos', 'permissoes' => [$manter->id, $incluir->id],
        ])->assertOk();

        $registro = Activity::with('subject')->sole();
        $this->assertSame('perfil-permissoes-alteradas', $registro->event);
        $this->assertTrue($registro->subject->is($perfil));
        $this->assertSame(['Ver obras'], $registro->properties['incluidas']);
        $this->assertSame(['Ver empresas'], $registro->properties['removidas']);
        $this->assertEqualsCanonicalizing([$manter->id, $incluir->id], $perfil->permissions()->pluck('id')->all());
    }

    public function test_criar_e_excluir_perfil_sao_registrados(): void
    {
        $perfil = Role::create(['name' => 'rh', 'display_name' => 'RH', 'description' => 'Recursos humanos']);
        $perfil->delete();

        $this->assertSame(['perfil-criado', 'perfil-excluido'], Activity::orderBy('id')->pluck('event')->all());
        $this->assertSame('RH', Activity::first()->properties['attributes']['display_name']);
    }

    public function test_acao_fora_do_vocabulario_e_recusada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Auditoria::registrar('acao-inventada');
    }
}
