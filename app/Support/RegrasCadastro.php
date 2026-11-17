<?php

namespace App\Support;

use App\Enums\TipoChavePix;
use App\Enums\TipoContaBancaria;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;

/** Regras de formulário comuns aos cadastros de PF e PJ: endereço e dados bancários. */
class RegrasCadastro
{
    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /** @return array<string, mixed> */
    public static function endereco(): array
    {
        return [
            'endereco' => ['nullable', 'array'],
            'endereco.cep' => ['nullable', 'regex:/^\d{5}-?\d{3}$/'],
            'endereco.logradouro' => ['nullable', 'string', 'max:150'],
            'endereco.numero' => ['nullable', 'string', 'max:20'],
            'endereco.complemento' => ['nullable', 'string', 'max:80'],
            'endereco.bairro' => ['nullable', 'string', 'max:80'],
            'endereco.cidade' => ['nullable', 'string', 'max:80'],
            'endereco.uf' => ['nullable', Rule::in(self::UFS)],
        ];
    }

    /** @return array<string, mixed> */
    public static function dadosBancarios(): array
    {
        return [
            'bancos' => ['nullable', 'array', 'max:10'],
            'bancos.*.id' => ['nullable', 'integer'],
            'bancos.*.banco' => ['required', 'string', 'max:80'],
            'bancos.*.agencia' => ['required', 'string', 'max:20'],
            'bancos.*.conta' => ['required', 'string', 'max:30'],
            'bancos.*.tipo' => ['required', Rule::enum(TipoContaBancaria::class)],
            'bancos.*.tipo_chave_pix' => ['nullable', Rule::enum(TipoChavePix::class)],
            'bancos.*.chave_pix' => ['nullable', 'string', 'max:140', 'required_with:bancos.*.tipo_chave_pix'],
            'principal' => ['nullable', 'integer'],
        ];
    }

    /** Erros da chave PIX conforme o tipo, na forma "bancos.N.chave_pix" => mensagem. */
    public static function errosChavePix(array $bancos): array
    {
        $erros = [];

        foreach ($bancos as $i => $conta) {
            $tipo = TipoChavePix::tryFrom($conta['tipo_chave_pix'] ?? '');
            $chave = trim((string) ($conta['chave_pix'] ?? ''));

            if ($tipo === null || $chave === '') {
                continue;
            }

            $valida = match ($tipo) {
                TipoChavePix::Cpf => Cpf::valido($chave),
                TipoChavePix::Cnpj => Cnpj::valido($chave),
                TipoChavePix::Email => filter_var($chave, FILTER_VALIDATE_EMAIL) !== false,
                TipoChavePix::Telefone => (bool) preg_match('/^\+?\d{10,13}$/', preg_replace('/[\s().-]/', '', $chave)),
                TipoChavePix::Aleatoria => (bool) preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $chave),
            };

            if (! $valida) {
                $erros["bancos.{$i}.chave_pix"] = 'A chave PIX da conta '.($i + 1).' não é uma chave '.mb_strtolower($tipo->rotulo()).' válida.';
            }
        }

        return $erros;
    }
}
