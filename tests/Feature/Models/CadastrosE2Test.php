<?php

namespace Tests\Feature\Models;

use App\Enums\StatusColaborador;
use App\Models\Cargo;
use App\Models\CargoQualificacao;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\ColaboradorQualificacao;
use App\Models\Configuracao;
use App\Models\DadoBancario;
use App\Models\Documento;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\QualificacaoTipo;
use App\Models\Reajuste;
use App\Models\Remuneracao;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastrosE2Test extends TestCase
{
    use RefreshDatabase;

    public function test_cada_factory_cria_um_registro_valido_com_autoria(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        foreach ([
            Cargo::class, Equipe::class, Colaborador::class, EquipeGestor::class, ColaboradorEquipe::class,
            DadoBancario::class, Documento::class, Reajuste::class, Remuneracao::class, QualificacaoTipo::class,
            CargoQualificacao::class, ColaboradorQualificacao::class, Configuracao::class,
        ] as $classe) {
            $registro = $classe::factory()->create();
            $this->assertDatabaseHas($registro->getTable(), ['id' => $registro->id, 'created_by' => $usuario->id]);
        }
    }

    public function test_colaborador_liga_pf_pj_cargo_e_nasce_em_pre_admissao(): void
    {
        $cargo = Cargo::factory()->create();
        $colaborador = Colaborador::factory()->create(['cargo_id' => $cargo->id]);

        $colaborador = Colaborador::with(['pessoaFisica', 'pessoaJuridica', 'cargo'])->find($colaborador->id);

        $this->assertSame(StatusColaborador::PreAdmissao, $colaborador->status);
        $this->assertInstanceOf(PessoaFisica::class, $colaborador->pessoaFisica);
        $this->assertInstanceOf(PessoaJuridica::class, $colaborador->pessoaJuridica);
        $this->assertTrue($colaborador->cargo->is($cargo));
    }

    public function test_pf_pode_ter_historico_com_mais_de_uma_pj(): void
    {
        $pf = PessoaFisica::factory()->create();
        Colaborador::factory()->create(['pessoa_fisica_id' => $pf->id, 'status' => StatusColaborador::Desligado]);
        Colaborador::factory()->create(['pessoa_fisica_id' => $pf->id, 'status' => StatusColaborador::Ativo]);

        $this->assertSame(2, $pf->colaboradores()->count());
    }

    public function test_dados_bancarios_e_documentos_servem_pf_pj_e_colaborador(): void
    {
        $pf = PessoaFisica::factory()->create();
        $pj = PessoaJuridica::factory()->create();

        DadoBancario::factory()->create(['pessoa_type' => 'pessoa-fisica', 'pessoa_id' => $pf->id, 'principal' => true]);
        DadoBancario::factory()->create(['pessoa_type' => 'pessoa-juridica', 'pessoa_id' => $pj->id]);
        Documento::factory()->create(['documentavel_type' => 'pessoa-juridica', 'documentavel_id' => $pj->id, 'tipo' => 'Contrato social']);

        $this->assertSame(1, $pf->dadosBancarios()->count());
        $this->assertSame(1, $pj->dadosBancarios()->count());
        $this->assertSame('Contrato social', $pj->documentos()->first()->tipo);
        $this->assertTrue(DadoBancario::with('pessoa')->first()->pessoa->is($pf));
    }

    public function test_cargo_e_tipo_de_qualificacao_tem_nome_unico(): void
    {
        Cargo::factory()->create(['nome' => 'Pedreiro']);
        $this->expectException(UniqueConstraintViolationException::class);
        Cargo::factory()->create(['nome' => 'Pedreiro']);
    }

    public function test_matriz_de_exigencias_nao_repete_o_par_cargo_e_tipo(): void
    {
        $cargo = Cargo::factory()->create();
        $tipo = QualificacaoTipo::factory()->create();
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $tipo->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $tipo->id]);
    }

    public function test_vigencia_tem_fim_exclusivo_e_so_uma_linha_vale_em_cada_dia(): void
    {
        $colaborador = Colaborador::factory()->create();
        Remuneracao::factory()->create([
            'colaborador_id' => $colaborador->id, 'valor_mensal' => 4000,
            'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-05-10',
        ]);
        Remuneracao::factory()->create([
            'colaborador_id' => $colaborador->id, 'valor_mensal' => 4400,
            'vigencia_inicio' => '2026-05-10', 'vigencia_fim' => null,
        ]);

        $em = fn (string $dia) => Remuneracao::vigentesEm($dia)->where('colaborador_id', $colaborador->id)->pluck('valor_mensal')->map(fn ($v) => (float) $v)->all();

        $this->assertSame([4000.0], $em('2026-05-09'));
        $this->assertSame([4400.0], $em('2026-05-10'));
        $this->assertSame([4400.0], $em('2027-01-01'));
        $this->assertSame([], $em('2025-12-31'));
        $this->assertSame(1, Remuneracao::vigentes()->count());
    }

    public function test_vinculos_de_equipe_e_de_gestor_valem_por_data(): void
    {
        $equipe = Equipe::factory()->create();
        $colaborador = Colaborador::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $colaborador->id, 'equipe_id' => $equipe->id, 'inicio' => '2026-02-01', 'fim' => '2026-06-01']);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'inicio' => '2026-01-01', 'fim' => null]);

        $this->assertSame(1, ColaboradorEquipe::vigentesEm('2026-05-31')->count());
        $this->assertSame(0, ColaboradorEquipe::vigentesEm('2026-06-01')->count());
        $this->assertSame(1, EquipeGestor::vigentesEm('2030-01-01')->count());
    }

    public function test_reajuste_guarda_criterio_json_e_gera_varias_remuneracoes(): void
    {
        $reajuste = Reajuste::factory()->create(['tipo' => 'lote', 'criterio' => ['cargo_id' => 7]]);
        Remuneracao::factory()->count(2)->create(['reajuste_id' => $reajuste->id]);

        $this->assertSame(['cargo_id' => 7], $reajuste->fresh()->criterio);
        $this->assertSame(2, $reajuste->remuneracoes()->count());
    }

    public function test_configuracao_guarda_valor_json_e_chave_unica(): void
    {
        Configuracao::create(['chave' => 'alertas.documentos', 'valor' => ['dias' => [30, 7]]]);

        $this->assertSame([30, 7], Configuracao::where('chave', 'alertas.documentos')->first()->valor['dias']);
        $this->expectException(UniqueConstraintViolationException::class);
        Configuracao::create(['chave' => 'alertas.documentos', 'valor' => []]);
    }

    public function test_pf_e_pj_guardam_uau_alterado_em(): void
    {
        $pf = PessoaFisica::factory()->create(['uau_alterado_em' => '2026-11-01 10:00:00']);

        $this->assertSame('2026-11-01 10:00', $pf->fresh()->uau_alterado_em->format('Y-m-d H:i'));
    }
}
