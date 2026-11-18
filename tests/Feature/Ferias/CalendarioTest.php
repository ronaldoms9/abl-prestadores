<?php

namespace Tests\Feature\Ferias;

use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Enums\AbrangenciaFeriado;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\Feriado;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\Obra;
use App\Models\Ocorrencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function colaborador(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-03-02']);
    }

    private function ferias(Colaborador $c, string $inicio, string $fim, StatusSolicitacaoFerias $status = StatusSolicitacaoFerias::Aprovada): FeriasSolicitacao
    {
        $periodo = FeriasPeriodo::where('colaborador_id', $c->id)->first() ?? FeriasPeriodo::factory()->create(['colaborador_id' => $c->id]);

        return FeriasSolicitacao::factory()->create(['colaborador_id' => $c->id, 'ferias_periodo_id' => $periodo->id, 'inicio' => $inicio, 'fim' => $fim, 'dias' => 10, 'status' => $status]);
    }

    private function eventos(array $filtros = []): array
    {
        return $this->getJson(route('ferias.calendario.eventos', $filtros + ['start' => '2026-08-01', 'end' => '2026-09-01']))->assertOk()->json();
    }

    public function test_mostra_ferias_pendentes_e_aprovadas_ocorrencias_e_feriados_com_fim_exclusivo(): void
    {
        $this->actingAs($this->usuarioCom('ferias-list'));
        $a = $this->colaborador();
        $b = $this->colaborador();
        $this->ferias($a, '2026-08-03', '2026-08-12');
        $this->ferias($b, '2026-08-17', '2026-08-21', StatusSolicitacaoFerias::Pendente);
        $this->ferias($b, '2026-08-24', '2026-08-28', StatusSolicitacaoFerias::Cancelada);
        Ocorrencia::factory()->create(['colaborador_id' => $a->id, 'inicio' => '2026-08-14', 'fim' => '2026-08-14', 'abona' => true]);
        Feriado::factory()->create(['data' => '2026-08-15', 'descricao' => 'Feriado de teste']);

        $eventos = collect($this->eventos());

        $ferias = $eventos->firstWhere('classNames', ['ev-ferias']);
        $this->assertSame(['2026-08-03', '2026-08-13'], [$ferias['start'], $ferias['end']], 'O fim do calendário é exclusivo');
        $this->assertStringContainsString($a->pessoaFisica->nome, $ferias['title']);
        $this->assertNotNull($eventos->firstWhere('classNames', ['ev-ferias-pendente']));
        $this->assertStringContainsString('falta abonada', $eventos->firstWhere('classNames', ['ev-falta'])['title']);
        $this->assertSame('Feriado de teste', $eventos->firstWhere('classNames', ['ev-feriado'])['title']);
        $this->assertCount(4, $eventos, 'A cancelada não aparece');
    }

    public function test_filtra_por_obra_e_por_equipe_com_vinculo_no_periodo(): void
    {
        $this->actingAs($this->usuarioCom('ferias-list'));
        $obra = Obra::factory()->create();
        $daObra = $this->colaborador(['obra_id' => $obra->id]);
        $semObra = $this->colaborador();
        $this->ferias($daObra, '2026-08-03', '2026-08-12');
        $this->ferias($semObra, '2026-08-03', '2026-08-12');
        Feriado::factory()->create(['data' => '2026-08-20', 'descricao' => 'Municipal da obra', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $obra->id]);
        Feriado::factory()->create(['data' => '2026-08-21', 'descricao' => 'Municipal de outra', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => Obra::factory()->create()->id]);

        $porObra = collect($this->eventos(['obra_id' => $obra->id]));
        $this->assertCount(2, $porObra->whereIn('classNames', [['ev-ferias'], ['ev-feriado']]));
        $this->assertTrue($porObra->contains(fn ($e) => $e['title'] === 'Municipal da obra'));
        $this->assertFalse($porObra->contains(fn ($e) => $e['title'] === 'Municipal de outra'));

        $equipe = Equipe::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $semObra->id, 'equipe_id' => $equipe->id, 'inicio' => '2026-08-10', 'fim' => null]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $daObra->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => '2026-07-31']);
        $porEquipe = collect($this->eventos(['equipe_id' => $equipe->id]))->where('classNames', ['ev-ferias']);
        $this->assertCount(1, $porEquipe);
        $this->assertStringContainsString($semObra->pessoaFisica->nome, $porEquipe->first()['title']);
    }

    public function test_gestor_ve_so_as_equipes_que_gere(): void
    {
        $gestor = $this->usuarioCom('ferias-list', 'equipe-gerir');
        $meu = $this->colaborador();
        $alheio = $this->colaborador();
        $equipe = Equipe::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => null]);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $this->ferias($meu, '2026-08-03', '2026-08-12');
        $this->ferias($alheio, '2026-08-03', '2026-08-12');

        $this->actingAs($gestor);
        $titulos = collect($this->eventos())->pluck('title')->implode('|');

        $this->assertStringContainsString($meu->pessoaFisica->nome, $titulos);
        $this->assertStringNotContainsString($alheio->pessoaFisica->nome, $titulos);
    }

    public function test_tela_e_endpoint_exigem_permissao_e_validam_o_intervalo(): void
    {
        $this->actingAs($this->usuarioCom('colaborador-list'));
        $this->get(route('ferias.calendario'))->assertForbidden();
        $this->getJson(route('ferias.calendario.eventos', ['start' => '2026-08-01', 'end' => '2026-09-01']))->assertForbidden();

        $this->actingAs($this->usuarioCom('ferias-list'));
        $this->get(route('ferias.calendario'))->assertOk()->assertSee('calendario');
        $this->getJson(route('ferias.calendario.eventos', ['start' => '2026-01-01', 'end' => '2026-12-31']))->assertStatus(422);
        $this->getJson(route('ferias.calendario.eventos', ['end' => '2026-12-31']))->assertStatus(422);
    }
}
