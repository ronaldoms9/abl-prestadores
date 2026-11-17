<?php

namespace App\Actions\Cadastros;

use App\Models\DadoBancario;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;

/**
 * Deixa as contas da pessoa iguais à lista enviada pelo formulário: atualiza
 * as que têm id, cria as novas e exclui as que saíram. Há exatamente uma
 * principal quando existe alguma conta (a primeira, se o formulário não disser).
 */
class SincronizarDadosBancarios
{
    /**
     * @param  array<int, array<string, mixed>>  $contas
     * @param  int|string|null  $principal  Posição da conta principal na lista.
     */
    public function executar(PessoaFisica|PessoaJuridica $pessoa, array $contas, int|string|null $principal = null): void
    {
        $contas = array_values($contas);
        $principal = ($principal !== null && isset($contas[(int) $principal])) ? (int) $principal : 0;

        DB::transaction(function () use ($pessoa, $contas, $principal) {
            $existentes = $pessoa->dadosBancarios()->get()->keyBy('id');
            $mantidas = [];
            $alterou = false;

            foreach ($contas as $posicao => $dados) {
                $atributos = [
                    'banco' => trim($dados['banco']),
                    'agencia' => trim($dados['agencia']),
                    'conta' => trim($dados['conta']),
                    'tipo' => $dados['tipo'],
                    'tipo_chave_pix' => ($dados['chave_pix'] ?? '') !== '' ? ($dados['tipo_chave_pix'] ?? null) : null,
                    'chave_pix' => ($dados['chave_pix'] ?? '') !== '' ? trim($dados['chave_pix']) : null,
                    'principal' => $posicao === $principal,
                ];

                /** @var DadoBancario|null $conta */
                $conta = isset($dados['id']) ? $existentes->get((int) $dados['id']) : null;

                if ($conta) {
                    $conta->fill($atributos);
                    $alterou = $alterou || $conta->isDirty();
                    $conta->save();
                } else {
                    $conta = $pessoa->dadosBancarios()->create($atributos);
                    $alterou = true;
                }

                $mantidas[] = $conta->id;
            }

            $removidas = $existentes->keys()->diff($mantidas);
            if ($removidas->isNotEmpty()) {
                $pessoa->dadosBancarios()->whereIn('id', $removidas)->get()->each->delete();
                $alterou = true;
            }

            if ($alterou) {
                // Nunca a conta nem a chave: só quantas contas ficaram.
                Auditoria::registrar('pessoa-dados-bancarios-alterados', $pessoa, ['contas' => count($mantidas)]);
            }
        });
    }
}
