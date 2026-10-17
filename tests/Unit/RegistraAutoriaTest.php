<?php

namespace Tests\Unit;

use App\Models\Concerns\RegistraAutoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistraAutoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('registros_autoria', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->auditoria();
        });
    }

    public function test_macro_cria_as_colunas_de_auditoria(): void
    {
        $this->assertTrue(Schema::hasColumns('registros_autoria', [
            'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    public function test_usuario_logado_preenche_created_by_e_updated_by(): void
    {
        $autor = User::factory()->create();
        $this->actingAs($autor);

        $registro = $this->novoRegistro('primeiro');

        $this->assertSame($autor->id, $registro->created_by);
        $this->assertSame($autor->id, $registro->updated_by);
        $this->assertTrue($registro->criadoPor->is($autor));
        $this->assertTrue($registro->atualizadoPor->is($autor));
    }

    public function test_outro_usuario_editando_muda_so_updated_by(): void
    {
        [$autor, $editor] = User::factory()->count(2)->create();

        $this->actingAs($autor);
        $registro = $this->novoRegistro('primeiro');

        $this->actingAs($editor);
        $registro->update(['nome' => 'editado']);

        $registro->refresh();
        $this->assertSame($autor->id, $registro->created_by);
        $this->assertSame($editor->id, $registro->updated_by);
    }

    public function test_sem_usuario_cria_sem_autor_e_edicao_mantem_o_ultimo_autor(): void
    {
        $semAutor = $this->novoRegistro('do job');
        $this->assertNull($semAutor->created_by);
        $this->assertNull($semAutor->updated_by);

        $autor = User::factory()->create();
        $this->actingAs($autor);
        $registro = $this->novoRegistro('humano');

        Auth::logout();
        $registro->update(['nome' => 'sincronizado']);

        $registro->refresh();
        $this->assertSame($autor->id, $registro->created_by);
        $this->assertSame($autor->id, $registro->updated_by);
    }

    private function novoRegistro(string $nome): Model
    {
        $model = new class extends Model
        {
            use RegistraAutoria;

            protected $table = 'registros_autoria';

            protected $guarded = [];
        };

        return $model->newQuery()->create(['nome' => $nome]);
    }
}
