<?php

namespace App\Actions\Remuneracoes;

use App\Enums\FormaReajuste;
use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Prévia do reajuste em lote: para cada colaborador do critério, o valor atual,
 * o novo e a diferença, sem gravar nada. Quem não tem remuneração vigente fica
 * de fora (listado em "ignorados"); linhas com erro bloqueiam a confirmação.
 *
 * Critério: ['tipo' => 'cargo', 'cargo_id' => 3] ou ['tipo' => 'lista', 'colaborador_ids' => [1, 2]].
 * O resultado traz um hash do que foi mostrado, conferido na confirmação.
 */
class PreverReajusteLote
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /**
     * @param  array<string, mixed>  $criterio
     * @return array{linhas: list<array<string, mixed>>, ignorados: list<array<string, mixed>>, erros: int, total_atual: int, total_novo: int, hash: string}
     */
    public function executar(array $criterio, FormaReajuste $forma, string|int|float $valor, CarbonInterface $vigenciaInicio): array
    {
        if (Dinheiro::centavos($valor) <= 0) {
            throw ValidationException::withMessages(['valor' => 'Informe um valor de reajuste maior que zero.']);
        }

        $inicio = $vigenciaInicio->copy()->startOfDay();
        if ($this->competencias->fechada($inicio)) {
            throw ValidationException::withMessages(['vigencia' => 'A competência de '.$inicio->format('m/Y').' já está fechada. Reajuste retroativo não é permitido.']);
        }

        $colaboradores = $this->colaboradores($criterio);
        if ($colaboradores->isEmpty()) {
            throw ValidationException::withMessages(['criterio' => 'Nenhum colaborador em pré-admissão ou ativo atende ao critério.']);
        }

        $vigentes = Remuneracao::query()
            ->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->vigentes()
            ->get()
            ->keyBy('colaborador_id');

        $linhas = [];
        $ignorados = [];

        foreach ($colaboradores as $colaborador) {
            $atual = $vigentes->get($colaborador->id);
            $nome = $colaborador->pessoaFisica->nome;

            if ($atual === null) {
                $ignorados[] = ['colaborador_id' => $colaborador->id, 'nome' => $nome, 'motivo' => 'sem remuneração vigente'];

                continue;
            }

            $centavosAtual = Dinheiro::centavos($atual->valor_mensal);
            $centavosNovo = Dinheiro::reajustar($centavosAtual, $forma, $valor);
            $erro = $inicio->lte($atual->vigencia_inicio)
                ? 'a vigência precisa ser posterior a '.$atual->vigencia_inicio->format('d/m/Y').', início da remuneração atual'
                : ($centavosNovo <= 0 ? 'o novo valor não é positivo' : null);

            $linhas[] = [
                'colaborador_id' => $colaborador->id,
                'nome' => $nome,
                'cargo' => $colaborador->cargo?->nome,
                'atual' => $centavosAtual,
                'novo' => $centavosNovo,
                'diferenca' => $centavosNovo - $centavosAtual,
                'aviso_faixa' => $colaborador->cargo?->avisoDeFaixa($centavosNovo),
                'erro' => $erro,
            ];
        }

        if ($linhas === []) {
            throw ValidationException::withMessages(['criterio' => 'Nenhum dos colaboradores do critério tem remuneração vigente.']);
        }

        return [
            'linhas' => $linhas,
            'ignorados' => $ignorados,
            'erros' => count(array_filter($linhas, fn ($l) => $l['erro'] !== null)),
            'total_atual' => array_sum(array_column($linhas, 'atual')),
            'total_novo' => array_sum(array_column($linhas, 'novo')),
            'hash' => self::hash($linhas, $forma, $valor, $inicio),
        ];
    }

    /** Resumo do que foi mostrado: se algo mudar até a confirmação, o hash muda. */
    public static function hash(array $linhas, FormaReajuste $forma, string|int|float $valor, CarbonInterface $inicio): string
    {
        return sha1(json_encode([
            array_map(fn ($l) => [$l['colaborador_id'], $l['atual'], $l['novo']], $linhas),
            $forma->value, Dinheiro::centavos($valor), $inicio->toDateString(),
        ]));
    }

    /** @return Collection<int, Colaborador> */
    private function colaboradores(array $criterio): Collection
    {
        $query = Colaborador::query()
            ->whereIn('status', StatusColaborador::abertos())
            ->with(['pessoaFisica', 'cargo'])
            ->orderBy('id');

        match ($criterio['tipo'] ?? null) {
            'cargo' => $query->where('cargo_id', (int) ($criterio['cargo_id'] ?? 0)),
            'lista' => $query->whereIn('id', array_map('intval', $criterio['colaborador_ids'] ?? [])),
            default => throw ValidationException::withMessages(['criterio' => 'Escolha o cargo ou a lista de colaboradores.']),
        };

        return $query->get();
    }
}
