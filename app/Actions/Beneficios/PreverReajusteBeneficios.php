<?php

namespace App\Actions\Beneficios;

use App\Enums\FormaReajuste;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Prévia do reajuste em lote de benefícios: para cada benefício escolhido, o
 * valor atual, o novo e a diferença, sem gravar nada. Benefício sem valor
 * vigente fica de fora ("ignorados"); linhas com erro bloqueiam a confirmação.
 * O reajuste mexe na tabela, não nos valores personalizados dos colaboradores.
 * O resultado traz um hash do que foi mostrado, conferido na confirmação.
 */
class PreverReajusteBeneficios
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /**
     * @param  array<int, int>  $beneficioIds
     * @return array{linhas: list<array<string, mixed>>, ignorados: list<array<string, mixed>>, erros: int, total_atual: int, total_novo: int, hash: string}
     */
    public function executar(array $beneficioIds, FormaReajuste $forma, string|int|float $valor, CarbonInterface $vigenciaInicio): array
    {
        if (Dinheiro::centavos($valor) <= 0) {
            throw ValidationException::withMessages(['valor' => 'Informe um valor de reajuste maior que zero.']);
        }

        $inicio = $vigenciaInicio->copy()->startOfDay();
        if ($this->competencias->fechada($inicio)) {
            throw ValidationException::withMessages(['vigencia' => 'A competência de '.$inicio->format('m/Y').' já está fechada. Mudança de valor retroativa não é permitida.']);
        }

        $beneficios = $this->beneficios($beneficioIds);
        if ($beneficios->isEmpty()) {
            throw ValidationException::withMessages(['beneficios' => 'Escolha ao menos um benefício ativo.']);
        }

        $vigentes = BeneficioValor::query()
            ->whereIn('beneficio_id', $beneficios->pluck('id'))
            ->vigentes()
            ->get()
            ->keyBy('beneficio_id');

        $linhas = [];
        $ignorados = [];

        foreach ($beneficios as $beneficio) {
            $atual = $vigentes->get($beneficio->id);

            if ($atual === null) {
                $ignorados[] = ['beneficio_id' => $beneficio->id, 'nome' => $beneficio->nome, 'motivo' => 'sem valor vigente'];

                continue;
            }

            $centavosAtual = Dinheiro::centavos($atual->valor);
            $centavosNovo = Dinheiro::reajustar($centavosAtual, $forma, $valor);
            $erro = $inicio->lte($atual->vigencia_inicio)
                ? 'a vigência precisa ser posterior a '.$atual->vigencia_inicio->format('d/m/Y').', início do valor atual'
                : ($centavosNovo <= 0 ? 'o novo valor não é positivo' : null);

            $linhas[] = [
                'beneficio_id' => $beneficio->id,
                'nome' => $beneficio->nome,
                'tipo' => $beneficio->tipo_calculo->rotulo(),
                'atual' => $centavosAtual,
                'novo' => $centavosNovo,
                'diferenca' => $centavosNovo - $centavosAtual,
                'erro' => $erro,
            ];
        }

        if ($linhas === []) {
            throw ValidationException::withMessages(['beneficios' => 'Nenhum dos benefícios escolhidos tem valor vigente.']);
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
            array_map(fn ($l) => [$l['beneficio_id'], $l['atual'], $l['novo']], $linhas),
            $forma->value, Dinheiro::centavos($valor), $inicio->toDateString(),
        ]));
    }

    /** @return Collection<int, Beneficio> */
    private function beneficios(array $ids): Collection
    {
        return Beneficio::query()->ativos()->whereIn('id', array_map('intval', $ids))->orderBy('nome')->get();
    }
}
