<?php

namespace App\Actions\Uau;

use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Mappers\ObraMapper;
use App\Integrations\Uau\UauGateway;
use App\Models\Empresa;
use App\Models\Obra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traz as obras ativas do UAU para a tabela obras (D5.2 a D5.5 da fatia 5).
 *
 * - Chave: (empresa_id, uau_codigo); o mesmo código existe em empresas diferentes.
 * - Obra de empresa que não existe localmente é ignorada (não inventa empresa).
 * - Inativa só as obras das empresas que vieram na lista: uma empresa que não
 *   veio, ou foi ignorada, não tem as obras desativadas por engano.
 * - Lista vazia ou que desativaria demais lança ImportacaoSuspensa sem gravar.
 */
class ImportarObras
{
    public function __construct(private readonly UauGateway $uau)
    {
    }

    public function executar(int $tentativa = 1): ResumoImportacao
    {
        $obrasUau = $this->uau->comContexto(tentativa: $tentativa)->obrasAtivas();

        $empresas = Empresa::withTrashed()->get()->keyBy(fn (Empresa $empresa) => (int) $empresa->uau_codigo);

        [$conhecidas, $ignoradas] = collect($obrasUau)
            ->partition(fn (ObraUau $obra) => $empresas->has($obra->empresaCodigo));

        $chaves = $conhecidas
            ->map(fn (ObraUau $obra) => self::chave($empresas->get($obra->empresaCodigo)->getKey(), $obra->codigo))
            ->all();
        $empresasNaLista = $conhecidas
            ->map(fn (ObraUau $obra) => $empresas->get($obra->empresaCodigo)->getKey())
            ->unique()->values()->all();

        $aInativar = Obra::ativos()->whereIn('empresa_id', $empresasNaLista)->get(['id', 'empresa_id', 'uau_codigo'])
            ->reject(fn (Obra $obra) => in_array(self::chave($obra->empresa_id, $obra->uau_codigo), $chaves, true))
            ->modelKeys();

        ImportacaoSuspensa::verificar('obras', count($obrasUau), Obra::ativos()->count(), count($aInativar));

        $codigosIgnorados = $ignoradas
            ->map(fn (ObraUau $obra) => "{$obra->empresaCodigo}/{$obra->codigo}")
            ->values()->all();

        foreach ($codigosIgnorados as $codigo) {
            Log::warning("Importação de obras: obra {$codigo} ignorada, a empresa não existe no sistema.");
        }

        return DB::transaction(function () use ($conhecidas, $empresas, $aInativar, $codigosIgnorados) {
            $locais = Obra::withTrashed()->get()->keyBy(fn (Obra $obra) => self::chave($obra->empresa_id, $obra->uau_codigo));
            $criadas = $atualizadas = $reativadas = 0;

            foreach ($conhecidas as $obraUau) {
                $empresa = $empresas->get($obraUau->empresaCodigo);
                $chave = self::chave($empresa->getKey(), $obraUau->codigo);
                $obra = $locais->get($chave) ?? new Obra();

                match (Upsert::gravar($obra, ObraMapper::atributos($obraUau, $empresa))) {
                    Upsert::CRIADA => $criadas++,
                    Upsert::REATIVADA => $reativadas++,
                    Upsert::ATUALIZADA => $atualizadas++,
                    Upsert::SEM_MUDANCA => null,
                };

                $locais->put($chave, $obra);
            }

            $inativadas = Upsert::inativar(Obra::class, $aInativar);

            return new ResumoImportacao($criadas, $atualizadas, $reativadas, $inativadas, $codigosIgnorados);
        });
    }

    private static function chave(int|string $empresaId, string $codigo): string
    {
        return $empresaId.'|'.$codigo;
    }
}
