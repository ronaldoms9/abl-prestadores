<?php

namespace App\Actions\Cadastros;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\ResponsavelPessoaJuridica;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Define a pessoa física responsável por uma pessoa jurídica, só no banco local.
 * É o passo que o cadastro de PJ chama ao salvar. Trocar o responsável encerra
 * o vínculo aberto (fim = data da troca) e cria outro; indicar quem já é o
 * responsável não muda nada.
 */
class DefinirResponsavel
{
    public function executar(PessoaJuridica $pessoaJuridica, PessoaFisica $responsavel, ?CarbonInterface $inicio = null): ResponsavelPessoaJuridica
    {
        $inicio = ($inicio ?? now())->copy()->startOfDay();

        return DB::transaction(function () use ($pessoaJuridica, $responsavel, $inicio) {
            $aberto = ResponsavelPessoaJuridica::query()
                ->where('pessoa_juridica_id', $pessoaJuridica->id)
                ->vigentes()
                ->lockForUpdate()
                ->get();

            $atual = $aberto->firstWhere('pessoa_fisica_id', $responsavel->id);

            foreach ($aberto->where('pessoa_fisica_id', '!=', $responsavel->id) as $anterior) {
                $anterior->update(['fim' => $inicio]);
            }

            return $atual ?? ResponsavelPessoaJuridica::create([
                'pessoa_juridica_id' => $pessoaJuridica->id,
                'pessoa_fisica_id' => $responsavel->id,
                'inicio' => $inicio,
            ]);
        });
    }
}
