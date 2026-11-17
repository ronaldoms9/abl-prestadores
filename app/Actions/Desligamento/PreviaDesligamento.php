<?php

namespace App\Actions\Desligamento;

use App\Actions\Beneficios\BeneficioVigente;
use App\Actions\Remuneracoes\RemuneracaoProporcional;
use App\Enums\StatusColaborador;
use App\Enums\TipoCalculoBeneficio;
use App\Models\Colaborador;
use App\Models\Configuracao;
use Carbon\CarbonInterface;

/**
 * Prévia dos valores finais do mês do desligamento: remuneração proporcional e
 * benefícios fixos. Não grava nada.
 *
 * Limite decidido (E3): até a E4 a prévia não inclui benefícios por dia
 * trabalhado, faltas nem feriados; eles entram no fechamento do mês. A tela
 * mostra esse aviso. O benefício fixo é proporcional aos dias ativos por
 * padrão (spec 4.2, a confirmar): configuracoes[calculo.beneficio_fixo_proporcional].
 */
class PreviaDesligamento
{
    public const AVISO = 'Esta prévia não inclui benefícios por dia trabalhado, faltas nem feriados: eles entram no fechamento do mês.';

    public function __construct(
        private readonly RemuneracaoProporcional $proporcional,
        private readonly BeneficioVigente $beneficios,
    ) {
    }

    /**
     * @return array{inicio: ?CarbonInterface, fim: CarbonInterface, dias: int, remuneracao: int, beneficios: list<array{nome: string, valor_mensal: int, valor: int, personalizado: bool}>, total_beneficios: int, total: int, avisos: list<string>}
     */
    public function executar(Colaborador $colaborador, CarbonInterface $dataDesligamento): array
    {
        $fim = $dataDesligamento->copy()->startOfDay();
        $avisos = [self::AVISO];

        // Quem nunca foi admitido não tem valores a pagar.
        if ($colaborador->status === StatusColaborador::PreAdmissao || $colaborador->data_admissao === null) {
            return ['inicio' => null, 'fim' => $fim, 'dias' => 0, 'remuneracao' => 0, 'beneficios' => [], 'total_beneficios' => 0, 'total' => 0, 'avisos' => ['O colaborador ainda não foi admitido: não há valores a pagar.']];
        }

        $inicioMes = $fim->copy()->startOfMonth();
        $inicio = $colaborador->data_admissao->gt($inicioMes) ? $colaborador->data_admissao->copy()->startOfDay() : $inicioMes;

        $remuneracao = $this->proporcional->valor($colaborador, $inicio, $fim);
        $proporcional = (bool) Configuracao::obter('calculo.beneficio_fixo_proporcional', true);

        $linhas = [];
        foreach ($this->beneficios->listaEm($colaborador, $fim) as $item) {
            if ($item['beneficio']->tipo_calculo !== TipoCalculoBeneficio::FixoMensal || $item['valor'] === null) {
                continue;
            }

            $inicioBeneficio = $item['vinculo']->inicio->gt($inicio) ? $item['vinculo']->inicio : $inicio;

            $linhas[] = [
                'nome' => $item['beneficio']->nome,
                'valor_mensal' => $item['valor'],
                'valor' => $proporcional ? $this->proporcional->proporcionar($item['valor'], $inicioBeneficio, $fim) : $item['valor'],
                'personalizado' => $item['personalizado'],
            ];
        }

        $totalBeneficios = array_sum(array_column($linhas, 'valor'));

        if ($remuneracao === 0) {
            $avisos[] = 'Não há remuneração vigente no período: confira o cadastro.';
        }

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'dias' => (int) $inicio->diffInDays($fim) + 1,
            'remuneracao' => $remuneracao,
            'beneficios' => $linhas,
            'total_beneficios' => $totalBeneficios,
            'total' => $remuneracao + $totalBeneficios,
            'avisos' => $avisos,
        ];
    }
}
