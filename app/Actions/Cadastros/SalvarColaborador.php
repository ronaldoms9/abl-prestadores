<?php

namespace App\Actions\Cadastros;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\VinculoSugerido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou altera um colaborador: a PF, a PJ pela qual presta serviço, o cargo,
 * a empresa e a obra (centro de custo). Nasce em pré-admissão; a admissão é da E3.
 *
 * Regras: uma PF tem um colaborador aberto (pré-admissão ou ativo) por vez, a
 * obra precisa ser da empresa escolhida, e a PJ escolhida que estava entre os
 * vínculos sugeridos da carga da planilha marca o sugerido como utilizado.
 */
class SalvarColaborador
{
    /**
     * @param  array<string, mixed>  $dados  Já validados.
     */
    public function executar(?Colaborador $colaborador, array $dados): Colaborador
    {
        return DB::transaction(function () use ($colaborador, $dados) {
            $novo = $colaborador === null;
            $colaborador ??= new Colaborador(['status' => StatusColaborador::PreAdmissao]);

            $pessoaFisicaId = $novo ? (int) $dados['pessoa_fisica_id'] : $colaborador->pessoa_fisica_id;

            if ($novo) {
                // Trava a PF para duas gravações simultâneas não abrirem dois colaboradores.
                $pessoa = PessoaFisica::query()->lockForUpdate()->findOrFail($pessoaFisicaId);

                if (Colaborador::where('pessoa_fisica_id', $pessoa->id)->whereIn('status', StatusColaborador::abertos())->exists()) {
                    throw ValidationException::withMessages(['pessoa_fisica_id' => 'Esta pessoa já é colaboradora ativa ou em pré-admissão. Desligue o vínculo atual antes de criar outro.']);
                }
            }

            $this->conferirObra($dados['empresa_id'] ?? null, $dados['obra_id'] ?? null);

            $colaborador->fill([
                'pessoa_fisica_id' => $pessoaFisicaId,
                'pessoa_juridica_id' => $dados['pessoa_juridica_id'],
                'cargo_id' => $dados['cargo_id'] ?? null,
                'empresa_id' => $dados['empresa_id'] ?? null,
                'obra_id' => $dados['obra_id'] ?? null,
            ])->save();

            VinculoSugerido::query()
                ->where('pessoa_fisica_id', $pessoaFisicaId)
                ->where('pessoa_juridica_id', $dados['pessoa_juridica_id'])
                ->whereNull('utilizado_em')
                ->update(['utilizado_em' => now()]);

            return $colaborador;
        });
    }

    private function conferirObra(mixed $empresaId, mixed $obraId): void
    {
        if ($obraId === null || $obraId === '') {
            return;
        }

        if ($empresaId === null || $empresaId === '' || ! Obra::where('id', $obraId)->where('empresa_id', $empresaId)->exists()) {
            throw ValidationException::withMessages(['obra_id' => 'A obra escolhida não pertence à empresa informada.']);
        }
    }
}
