<?php

namespace App\Support\Ferias;

use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAprovadorFerias;
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
    public function doNivel(FeriasSolicitacao $solicitacao, FeriasNivel $nivel, CarbonInterface $data): Collection
    {
        $usuarios = match ($nivel->tipo_aprovador) {
            TipoAprovadorFerias::GestorEquipe => $this->gestoresDasEquipes($solicitacao, $data),
            TipoAprovadorFerias::Permissao => $nivel->permissao === null ? collect() : $this->comPermissao($nivel->permissao->name),
        };

        return $this->semOsImpedidos($usuarios, $solicitacao);
    }

    /** @return Collection<int, User> */
    public function qualquer(FeriasSolicitacao $solicitacao): Collection
    {
        return $this->semOsImpedidos($this->comPermissao(self::PERMISSAO_QUALQUER), $solicitacao);
    }

    /**
     * Nível que de fato espera decisão agora, pulando os que estão vazios.
     *
     * @return array{nivel: int, usuarios: Collection<int, User>, puladas: list<array{nivel: int, motivo: string}>, ultimo: bool, fallback: bool}
     */
    public function resolver(FeriasSolicitacao $solicitacao, CarbonInterface $data): array
    {
        $niveis = FeriasNivel::query()->with('permissao')->where('ativo', true)->orderBy('ordem')->get();
        $restantes = $niveis->filter(fn (FeriasNivel $n) => $n->ordem >= $solicitacao->nivel_atual)->values();
        $puladas = [];

        foreach ($restantes as $i => $nivel) {
            $usuarios = $this->doNivel($solicitacao, $nivel, $data);

            if ($usuarios->isNotEmpty()) {
                return ['nivel' => $nivel->ordem, 'usuarios' => $usuarios, 'puladas' => $puladas, 'ultimo' => $i === $restantes->count() - 1, 'fallback' => false];
            }

            $puladas[] = ['nivel' => $nivel->ordem, 'motivo' => 'Nenhum aprovador elegível no nível '.$nivel->ordem.' ('.$nivel->tipo_aprovador->rotulo().').'];
        }

        return [
            'nivel' => ($niveis->max('ordem') ?? 0) + 1,
            'usuarios' => $this->qualquer($solicitacao),
            'puladas' => $puladas,
            'ultimo' => true,
            'fallback' => true,
        ];
    }

    /** O usuário pode decidir esta solicitação hoje? */
    public function podeDecidir(FeriasSolicitacao $solicitacao, User $usuario, CarbonInterface $data): bool
    {
        return $solicitacao->status === StatusSolicitacaoFerias::Pendente
            && $this->resolver($solicitacao, $data)['usuarios']->contains('id', $usuario->id);
    }

    /** @return Collection<int, User> */
    private function gestoresDasEquipes(FeriasSolicitacao $solicitacao, CarbonInterface $data): Collection
    {
        $equipes = $solicitacao->colaborador->equipesVigentesEm($data)->select('equipes.id');

        $ids = EquipeGestor::query()->whereIn('equipe_id', $equipes)->vigentesEm($data)->pluck('user_id')->unique();

        return User::query()->ativos()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /** @return Collection<int, User> */
    private function comPermissao(string $permissao): Collection
    {
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
