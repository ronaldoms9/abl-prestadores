<?php

namespace App\Actions\Uau;

use App\Enums\ResultadoImportacao;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Data\TipoPessoaUau;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Integrations\Uau\Mappers\PessoaMapper;
use App\Integrations\Uau\UauGateway;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Support\Documento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Consulta uma pessoa no UAU pelo CPF/CNPJ e grava em pessoas_fisicas ou
 * pessoas_juridicas (D5.3 e D5.7 da fatia 5).
 *
 * Usada pelo ImportarPessoaJob (conciliação) e, linha a linha, pela carga da
 * planilha (fatia 5b), que precisa do resultado na hora. Valida só o tamanho
 * do documento; o dígito verificador é da rule Cpf/Cnpj, no upload.
 */
class ImportarPessoa
{
    public function __construct(private readonly UauGateway $uau)
    {
    }

    /**
     * @throws UauRejeitou quando o UAU devolve outra pessoa
     * @throws \App\Integrations\Uau\Exceptions\UauIndisponivel
     */
    public function executar(string $documento, int $tentativa = 1): ResultadoPessoa
    {
        $documento = Documento::digitos($documento) ?? '';
        $model = match (strlen($documento)) {
            11 => PessoaFisica::class,
            14 => PessoaJuridica::class,
            default => null,
        };

        if ($model === null) {
            return new ResultadoPessoa(ResultadoImportacao::Invalida);
        }

        // Fora de transação (D5.2): o rollback não pode apagar o log da chamada.
        $local = $this->porDocumento($model, $documento);
        $pessoaUau = $this->uau
            ->comContexto(entidade: $local, chaveIdempotencia: "pessoa:{$documento}", tentativa: $tentativa)
            ->pessoaPorDocumento($documento);

        if ($pessoaUau === null) {
            return $this->naoEncontrada($local);
        }

        if ($pessoaUau->documento !== $documento) {
            throw new UauRejeitou('O UAU devolveu a resposta de outra pessoa.');
        }

        try {
            [$pessoa, $existia] = $this->gravar($model, $pessoaUau);
        } catch (UniqueConstraintViolationException) {
            // Outro processo criou a mesma pessoa entre a busca e a gravação: busca de novo e atualiza.
            [$pessoa, $existia] = $this->gravar($model, $pessoaUau);
        }

        $resultado = match (true) {
            ! $pessoaUau->ativa => ResultadoImportacao::Inativa,
            $existia => ResultadoImportacao::JaExistia,
            default => ResultadoImportacao::Importada,
        };

        return new ResultadoPessoa($resultado, $pessoa);
    }

    /**
     * Upsert pelo documento; sem ele, pelo código do UAU (documento corrigido no UAU).
     *
     * @param  class-string<PessoaFisica|PessoaJuridica>  $model
     * @return array{0: PessoaFisica|PessoaJuridica, 1: bool}
     */
    private function gravar(string $model, PessoaUau $pessoaUau): array
    {
        return DB::transaction(function () use ($model, $pessoaUau) {
            $pessoa = $this->porDocumento($model, $pessoaUau->documento)
                ?? $model::withTrashed()->where('uau_codigo', $pessoaUau->codigo)->first()
                ?? new $model();
            $existia = $pessoa->exists;

            Upsert::gravar($pessoa, $pessoaUau->tipo === TipoPessoaUau::Fisica
                ? PessoaMapper::fisica($pessoaUau)
                : PessoaMapper::juridica($pessoaUau));

            return [$pessoa, $existia];
        });
    }

    /** Pessoa que o UAU não encontra mais vira inativa; excluída fica como está. */
    private function naoEncontrada(PessoaFisica|PessoaJuridica|null $local): ResultadoPessoa
    {
        if ($local !== null && ! $local->trashed() && $local->ativo) {
            Upsert::inativar($local::class, [$local->getKey()]);
            $local->refresh();
        }

        return new ResultadoPessoa(ResultadoImportacao::NaoEncontrada, $local);
    }

    /** @param  class-string<PessoaFisica|PessoaJuridica>  $model */
    private function porDocumento(string $model, string $documento): PessoaFisica|PessoaJuridica|null
    {
        $campo = $model === PessoaFisica::class ? 'cpf' : 'cnpj';

        return $model::withTrashed()->where($campo, $documento)->first();
    }
}
