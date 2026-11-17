<?php

namespace App\Actions\Cadastros;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Support\Auditoria;
use App\Support\Endereco;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou altera uma pessoa jurídica no banco local e define a pessoa física
 * responsável por ela (DefinirResponsavel). O responsável é obrigatório.
 */
class SalvarPessoaJuridica
{
    public function __construct(
        private readonly SincronizarDadosBancarios $bancos,
        private readonly DefinirResponsavel $responsavel,
    ) {
    }

    /**
     * @param  array<string, mixed>  $dados  Já validados.
     */
    public function executar(?PessoaJuridica $pessoa, array $dados): PessoaJuridica
    {
        return DB::transaction(function () use ($pessoa, $dados) {
            $pessoa ??= new PessoaJuridica;

            $pessoa->fill([
                'cnpj' => $dados['cnpj'],
                'razao_social' => trim($dados['razao_social']),
                'nome_fantasia' => $dados['nome_fantasia'] ?? null,
                'regime_tributario' => $dados['regime_tributario'] ?? null,
                'inscricao_municipal' => $dados['inscricao_municipal'] ?? null,
                'inscricao_estadual' => $dados['inscricao_estadual'] ?? null,
                'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'endereco' => Endereco::fromArray($dados['endereco'] ?? []),
                'ativo' => $dados['ativo'] ?? $pessoa->ativo ?? true,
            ])->save();

            $this->definirResponsavel($pessoa, PessoaFisica::findOrFail($dados['responsavel_id']));
            $this->bancos->executar($pessoa, $dados['bancos'] ?? [], $dados['principal'] ?? null);

            return $pessoa;
        });
    }

    private function definirResponsavel(PessoaJuridica $pessoa, PessoaFisica $responsavel): void
    {
        $atual = $pessoa->responsavelAtual()->with('pessoaFisica')->first();

        if ($atual?->pessoa_fisica_id === $responsavel->id) {
            return;
        }

        $this->responsavel->executar($pessoa, $responsavel);

        Auditoria::registrar('pessoa-juridica-responsavel-alterado', $pessoa, [
            'antes' => $atual?->pessoaFisica?->nome,
            'depois' => $responsavel->nome,
        ]);
    }
}
