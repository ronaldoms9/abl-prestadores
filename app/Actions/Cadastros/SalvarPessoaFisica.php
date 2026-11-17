<?php

namespace App\Actions\Cadastros;

use App\Models\PessoaFisica;
use App\Support\Endereco;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou altera uma pessoa física com os dados do formulário, no banco local.
 * Pessoa nova nasce sem código do UAU; a gravação lá é a fatia 4.
 */
class SalvarPessoaFisica
{
    public function __construct(private readonly SincronizarDadosBancarios $bancos)
    {
    }

    /**
     * @param  array<string, mixed>  $dados  Já validados.
     */
    public function executar(?PessoaFisica $pessoa, array $dados): PessoaFisica
    {
        return DB::transaction(function () use ($pessoa, $dados) {
            $pessoa ??= new PessoaFisica;

            $pessoa->fill([
                'cpf' => $dados['cpf'],
                'nome' => trim($dados['nome']),
                'nascimento' => $dados['nascimento'] ?? null,
                'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'endereco' => Endereco::fromArray($dados['endereco'] ?? []),
                'ativo' => $dados['ativo'] ?? $pessoa->ativo ?? true,
            ])->save();

            $this->bancos->executar($pessoa, $dados['bancos'] ?? [], $dados['principal'] ?? null);

            return $pessoa;
        });
    }
}
