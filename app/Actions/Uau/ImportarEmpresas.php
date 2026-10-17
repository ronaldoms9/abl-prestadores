<?php

namespace App\Actions\Uau;

use App\Integrations\Uau\Mappers\EmpresaMapper;
use App\Integrations\Uau\UauGateway;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * Traz as empresas ativas do UAU para a tabela empresas (D5.2 a D5.4 da fatia 5).
 *
 * - Chama o UAU fora de transação: um rollback não pode apagar o log da falha.
 * - Upsert por uau_codigo, com withTrashed(): restaura excluída, reativa
 *   inativa, nunca apaga.
 * - Ativas que não vieram na lista viram inativas; excluídas ficam como estão.
 * - Lista vazia ou que desativaria demais lança ImportacaoSuspensa sem gravar.
 */
class ImportarEmpresas
{
    public function __construct(private readonly UauGateway $uau)
    {
    }

    public function executar(int $tentativa = 1): ResumoImportacao
    {
        $empresasUau = $this->uau->comContexto(tentativa: $tentativa)->empresasAtivas();

        $codigos = array_map(fn ($empresa) => $empresa->codigo, $empresasUau);
        $ativas = Empresa::ativos()->get(['id', 'uau_codigo']);
        $aInativar = $ativas->reject(fn (Empresa $empresa) => in_array((int) $empresa->uau_codigo, $codigos, true))
            ->modelKeys();

        ImportacaoSuspensa::verificar('empresas', count($empresasUau), $ativas->count(), count($aInativar));

        return DB::transaction(function () use ($empresasUau, $aInativar) {
            $locais = Empresa::withTrashed()->get()->keyBy(fn (Empresa $empresa) => (int) $empresa->uau_codigo);
            $criadas = $atualizadas = $reativadas = 0;

            foreach ($empresasUau as $empresaUau) {
                $empresa = $locais->get($empresaUau->codigo) ?? new Empresa();

                match (Upsert::gravar($empresa, EmpresaMapper::atributos($empresaUau))) {
                    Upsert::CRIADA => $criadas++,
                    Upsert::REATIVADA => $reativadas++,
                    Upsert::ATUALIZADA => $atualizadas++,
                    Upsert::SEM_MUDANCA => null,
                };

                $locais->put($empresaUau->codigo, $empresa);
            }

            $inativadas = Upsert::inativar(Empresa::class, $aInativar);

            return new ResumoImportacao($criadas, $atualizadas, $reativadas, $inativadas);
        });
    }
}
