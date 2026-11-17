<?php

namespace App\Actions\Contratos;

use App\Enums\TipoContrato;
use App\Models\Configuracao;
use App\Models\Contrato;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Numeração AAAA/NNNN. Contrato: contador em `configuracoes` (uma linha por
 * ano, ou uma só quando não reinicia), lido com lock dentro da transação de
 * quem chama, então duas gerações ao mesmo tempo nunca pegam o mesmo número;
 * o índice único de contratos.numero é a segunda barreira. Aditivo:
 * "<número do pai>-A01", "-A02"... (o pai é travado). Distrato: "<pai>-D".
 * Prefixo e reinício anual: configuracoes[contratos.numeracao].
 */
class ProximoNumeroContrato
{
    /** @return array{numero: string, ano: int, sequencia: int} */
    public function para(TipoContrato $tipo, ?Contrato $pai, CarbonInterface $hoje): array
    {
        return match ($tipo) {
            TipoContrato::Contrato => $this->contrato($hoje),
            TipoContrato::Aditivo => $this->aditivo($pai),
            TipoContrato::Distrato => ['numero' => $pai->numero.'-D', 'ano' => $pai->ano, 'sequencia' => 1],
        };
    }

    /** @return array{numero: string, ano: int, sequencia: int} */
    private function contrato(CarbonInterface $hoje): array
    {
        $ajustes = array_merge(config('cadastros.contratos.numeracao'), (array) Configuracao::obter('contratos.numeracao', []));
        $ano = $hoje->year;
        $chave = ($ajustes['reinicia_anualmente'] ?? true) ? "contratos.sequencia.{$ano}" : 'contratos.sequencia.geral';

        Configuracao::withTrashed()->firstOrCreate(['chave' => $chave], ['valor' => ['ultimo' => 0]]);
        $linha = Configuracao::withTrashed()->where('chave', $chave)->lockForUpdate()->firstOrFail();

        // Nunca volta atrás: se o contador estiver atrasado em relação ao que já existe, continua do maior.
        $maiorExistente = (int) Contrato::withTrashed()->where('tipo', TipoContrato::Contrato->value)->where('ano', $ano)->max('sequencia');
        $proximo = max((int) ($linha->valor['ultimo'] ?? 0), $maiorExistente) + 1;
        $linha->update(['valor' => ['ultimo' => $proximo]]);

        return [
            'numero' => ($ajustes['prefixo'] ?? '').$ano.'/'.str_pad((string) $proximo, 4, '0', STR_PAD_LEFT),
            'ano' => $ano,
            'sequencia' => $proximo,
        ];
    }

    /** @return array{numero: string, ano: int, sequencia: int} */
    private function aditivo(Contrato $pai): array
    {
        DB::table('contratos')->where('id', $pai->id)->lockForUpdate()->first();

        $proximo = (int) Contrato::withTrashed()->where('contrato_pai_id', $pai->id)->where('tipo', TipoContrato::Aditivo->value)->max('sequencia') + 1;

        return [
            'numero' => $pai->numero.'-A'.str_pad((string) $proximo, 2, '0', STR_PAD_LEFT),
            'ano' => $pai->ano,
            'sequencia' => $proximo,
        ];
    }
}
