<?php

namespace App\Actions\Integracoes;

use App\Enums\DirecaoIntegracao;
use App\Enums\StatusIntegracao;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Integrations\Uau\Jobs\ImportarPessoaJob;
use App\Integrations\Uau\OperacaoUau;
use App\Models\IntegracaoLog;
use App\Support\Auditoria;

/**
 * Despacha de novo a importação de uma chamada que falhou (D8.4 da fatia 8).
 * O resultado vira uma linha nova em integracao_logs, que marca a falha como
 * resolvida. O job respeita o ShouldBeUnique: se um igual já estiver na fila,
 * ele cobre o pedido.
 */
class ReprocessarFalha
{
    /** Motivo pelo qual a chamada não pode ser reprocessada, ou null se pode. */
    public static function motivoParaRecusar(IntegracaoLog $log): ?string
    {
        return match (true) {
            $log->status !== StatusIntegracao::Erro => 'Só uma chamada com erro pode ser reprocessada.',
            $log->direcao !== DirecaoIntegracao::Entrada => 'Só as importações do UAU podem ser reprocessadas.',
            ! in_array(OperacaoUau::tryFrom($log->operacao), [OperacaoUau::EmpresasAtivas, OperacaoUau::ObrasAtivas, OperacaoUau::PessoaPorDocumento], true)
                => 'Esta chamada não é uma importação; a próxima chamada ao UAU a repete.',
            $log->resolvida() => 'Esta falha já foi resolvida por uma chamada posterior.',
            default => null,
        };
    }

    /**
     * @throws NaoReprocessavel
     */
    public function executar(IntegracaoLog $log): string
    {
        if ($motivo = self::motivoParaRecusar($log)) {
            throw new NaoReprocessavel($motivo);
        }

        $operacao = OperacaoUau::from($log->operacao);
        $job = match ($operacao) {
            OperacaoUau::EmpresasAtivas => new ImportarEmpresasJob(),
            OperacaoUau::ObrasAtivas => new ImportarObrasJob(),
            OperacaoUau::PessoaPorDocumento => new ImportarPessoaJob($this->documento($log)),
        };

        dispatch($job);

        Auditoria::registrar('integracao-reprocessada', $log, ['operacao' => $operacao->value]);

        return "{$operacao->rotulo()}: enviado para a fila. O resultado aparece nesta lista em instantes.";
    }

    private function documento(IntegracaoLog $log): string
    {
        $documento = $log->payload['cpf_cnpj'] ?? null;

        if (! is_string($documento) || $documento === '') {
            throw new NaoReprocessavel('A chamada não guardou o CPF/CNPJ consultado.');
        }

        return $documento;
    }
}
