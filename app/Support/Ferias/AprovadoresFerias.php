<?php

namespace App\Support\Ferias;

use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAprovadorFerias;
use App\Models\ColaboradorEquipe;
use App\Models\EquipeGestor;
use App\Models\FeriasNivel;
use App\Models\FeriasSolicitacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Quem pode decidir uma solicitação de férias em uma data (README D7).
 * Não grava nada: SincronizarFilaFerias registra as escaladas.
 */
class AprovadoresFerias
{
    public const PERMISSAO_QUALQUER = 'ferias-aprovar-qualquer';

    /**
     * Aprovadores de um nível, sem o solicitante e sem o próprio colaborador.
     *
     * @return Collection<int, User>
     */
    public function doNivel(FeriasSolicitacao $solicitacao, FeriasNivel $nivel, CarbonInterface $data, ?array $lote = null): Collection
    {
        $usuarios = match ($nivel->tipo_aprovador) {
            TipoAprovadorFerias::GestorEquipe => $this->gestoresDasEquipes($solicitacao, $data, $lote),
            TipoAprovadorFerias::Permissao => $nivel->permissao === null ? collect() : $this->comPermissao($nivel->permissao->name, $lote),
        };

        return $this->semOsImpedidos($usuarios, $solicitacao);
    }

    /** @return Collection<int, User> */
    public function qualquer(FeriasSolicitacao $solicitacao, ?array $lote = null): Collection
    {
        return $this->semOsImpedidos($this->comPermissao(self::PERMISSAO_QUALQUER, $lote), $solicitacao);
    }

    /**
     * Carrega de uma vez o que `resolver` consulta (níveis, quem tem cada permissão e os gestores
     * das equipes dos colaboradores), para decidir muitas solicitações sem uma consulta por linha.
     *
     * @param  Collection<int, FeriasSolicitacao>  $solicitacoes
     * @return array{niveis: Collection<int, FeriasNivel>, permissoes: array<string, Collection<int, User>>, gestores: array<int, Collection<int, User>>}
     */
    public function carregarLote(Collection $solicitacoes, CarbonInterface $data): array
    {
        $niveis = FeriasNivel::query()->with('permissao')->where('ativo', true)->orderBy('ordem')->get();

        $permissoes = [];
        foreach ($niveis->pluck('permissao.name')->filter()->push(self::PERMISSAO_QUALQUER)->unique() as $nome) {
            $permissoes[$nome] = $this->comPermissao($nome);
        }

        $vinculos = ColaboradorEquipe::query()->whereIn('colaborador_id', $solicitacoes->pluck('colaborador_id')->unique())->vigentesEm($data)->get(['colaborador_id', 'equipe_id']);
        $gestoresPorEquipe = EquipeGestor::query()->whereIn('equipe_id', $vinculos->pluck('equipe_id')->unique())->vigentesEm($data)->get(['equipe_id', 'user_id'])->groupBy('equipe_id');
        $usuarios = User::query()->ativos()->whereIn('id', $gestoresPorEquipe->flatten(1)->pluck('user_id')->unique())->orderBy('id')->get()->keyBy('id');

        $gestores = [];
        foreach ($vinculos->groupBy('colaborador_id') as $colaboradorId => $linhas) {
            $ids = $linhas->flatMap(fn ($v) => ($gestoresPorEquipe[$v->equipe_id] ?? collect())->pluck('user_id'))->unique();
            $gestores[(int) $colaboradorId] = $usuarios->only($ids->all())->sortKeys()->values();
        }

        return ['niveis' => $niveis, 'permissoes' => $permissoes, 'gestores' => $gestores];
    }

    /**
     * Nível que de fato espera decisão agora, pulando os que estão vazios.
     *
     * @return array{nivel: int, usuarios: Collection<int, User>, puladas: list<array{nivel: int, motivo: string}>, ultimo: bool, fallback: bool}
     */
    public function resolver(FeriasSolicitacao $solicitacao, CarbonInterface $data, ?array $lote = null): array
    {
        $niveis = $lote['niveis'] ?? FeriasNivel::query()->with('permissao')->where('ativo', true)->orderBy('ordem')->get();
        $restantes = $niveis->filter(fn (FeriasNivel $n) => $n->ordem >= $solicitacao->nivel_atual)->values();
        $puladas = [];

        foreach ($restantes as $i => $nivel) {
            $usuarios = $this->doNivel($solicitacao, $nivel, $data, $lote);

            if ($usuarios->isNotEmpty()) {
                return ['nivel' => $nivel->ordem, 'usuarios' => $usuarios, 'puladas' => $puladas, 'ultimo' => $i === $restantes->count() - 1, 'fallback' => false];
            }

            $puladas[] = ['nivel' => $nivel->ordem, 'motivo' => 'Nenhum aprovador elegível no nível '.$nivel->ordem.' ('.$nivel->tipo_aprovador->rotulo().').'];
        }

        return [
            'nivel' => ($niveis->max('ordem') ?? 0) + 1,
            'usuarios' => $this->qualquer($solicitacao, $lote),
            'puladas' => $puladas,
            'ultimo' => true,
            'fallback' => true,
        ];
    }

    /** O usuário pode decidir esta solicitação hoje? */
    public function podeDecidir(FeriasSolicitacao $solicitacao, User $usuario, CarbonInterface $data, ?array $lote = null): bool
    {
        return $solicitacao->status === StatusSolicitacaoFerias::Pendente
            && $this->resolver($solicitacao, $data, $lote)['usuarios']->contains('id', $usuario->id);
    }

    /** @return Collection<int, User> */
    private function gestoresDasEquipes(FeriasSolicitacao $solicitacao, CarbonInterface $data, ?array $lote = null): Collection
    {
        if ($lote !== null) {
            return $lote['gestores'][$solicitacao->colaborador_id] ?? collect();
        }

        $equipes = $solicitacao->colaborador->equipesVigentesEm($data)->select('equipes.id');

        $ids = EquipeGestor::query()->whereIn('equipe_id', $equipes)->vigentesEm($data)->pluck('user_id')->unique();

        return User::query()->ativos()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /** @return Collection<int, User> */
    private function comPermissao(string $permissao, ?array $lote = null): Collection
    {
        if ($lote !== null && isset($lote['permissoes'][$permissao])) {
            return $lote['permissoes'][$permissao];
        }

        return User::query()->ativos()
            ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', $permissao))
            ->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, User>  $usuarios
     * @return Collection<int, User>
     */
    private function semOsImpedidos(Collection $usuarios, FeriasSolicitacao $solicitacao): Collection
    {
        $pessoaDoColaborador = $solicitacao->colaborador->pessoa_fisica_id;

        return $usuarios
            ->reject(fn (User $u) => $u->id === $solicitacao->solicitado_por || ($pessoaDoColaborador !== null && $u->pessoa_fisica_id === $pessoaDoColaborador))
            ->values();
    }
}
