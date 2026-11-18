<?php

namespace App\Actions\Notas;

use App\Enums\StatusNota;
use App\Models\Configuracao;
use App\Models\NotaFiscal;
use App\Support\Dinheiro;
use Carbon\Carbon;

/** Confere a nota com o cadastro e o fechamento e grava o resultado. Não decide: só marca enviada ou divergente. */
class ConferirNota
{
    /** @return list<array{regra: string, mensagem: string}> */
    public function executar(NotaFiscal $nota): array
    {
        $nota->loadMissing(['colaborador.pessoaJuridica', 'colaborador.empresa', 'competencia', 'fechamento']);
        $divergencias = [];
        $falha = function (string $regra, string $mensagem) use (&$divergencias) {
            $divergencias[] = ['regra' => $regra, 'mensagem' => $mensagem];
        };

        $faltando = array_keys(array_filter([
            'número' => blank($nota->numero),
            'data de emissão' => $nota->data_emissao === null,
            'CNPJ do emissor' => blank($nota->cnpj_emissor),
            'CNPJ do tomador' => blank($nota->cnpj_tomador),
            'valor bruto' => $nota->valor_bruto === null,
        ]));
        if ($faltando !== []) {
            $falha('dados', 'Dados da nota faltando: '.implode(', ', $faltando).'.');
        }

        $this->emissorETomador($nota, $falha);
        $this->valor($nota, $falha);
        $this->duplicidade($nota, $falha);
        $this->competencia($nota, $falha);

        $nota->update(['divergencias' => $divergencias === [] ? null : $divergencias, 'status' => $divergencias === [] ? StatusNota::Enviada : StatusNota::Divergente]);

        return $divergencias;
    }

    private function emissorETomador(NotaFiscal $nota, callable $falha): void
    {
        $pj = $nota->colaborador->pessoaJuridica;
        $empresa = $nota->colaborador->empresa;

        if (filled($nota->cnpj_emissor)) {
            if ($pj === null) {
                $falha('emissor', 'O colaborador não tem pessoa jurídica no cadastro para conferir o emissor.');
            } elseif ($pj->cnpj !== $nota->cnpj_emissor) {
                $falha('emissor', 'O CNPJ do emissor ('.$this->mascara($nota->cnpj_emissor).') não é o da pessoa jurídica do colaborador ('.$this->mascara($pj->cnpj).').');
            }
        }

        if (filled($nota->cnpj_tomador)) {
            if ($empresa === null) {
                $falha('tomador', 'O colaborador não tem empresa contratante no cadastro para conferir o tomador.');
            } elseif ($empresa->cnpj !== $nota->cnpj_tomador) {
                $falha('tomador', 'O CNPJ do tomador ('.$this->mascara($nota->cnpj_tomador).') não é o da empresa contratante ('.$this->mascara($empresa->cnpj).').');
            }
        }
    }

    private function valor(NotaFiscal $nota, callable $falha): void
    {
        if ($nota->valor_bruto === null) {
            return;
        }

        $esperado = Dinheiro::centavos($nota->fechamento->total_liquido);
        $informado = Dinheiro::centavos($nota->valor_bruto);
        $tolerancia = Dinheiro::centavos((string) Configuracao::obter('notas.tolerancia_valor', '0'));

        if (abs($esperado - $informado) > $tolerancia) {
            $falha('valor', 'O valor bruto da nota ('.Dinheiro::formatar($informado).') difere do fechamento ('.Dinheiro::formatar($esperado).')'.($tolerancia > 0 ? ' além da tolerância de '.Dinheiro::formatar($tolerancia) : '').'.');
        }
    }

    private function duplicidade(NotaFiscal $nota, callable $falha): void
    {
        $outras = NotaFiscal::query()->whereKeyNot($nota->id)->where('status', '!=', StatusNota::Recusada->value);

        if (filled($nota->chave_acesso) && (clone $outras)->where('chave_acesso', $nota->chave_acesso)->exists()) {
            $falha('duplicidade', 'Já existe outra nota com esta chave de acesso.');

            return;
        }

        if (filled($nota->cnpj_emissor) && filled($nota->numero)
            && (clone $outras)->where('cnpj_emissor', $nota->cnpj_emissor)->where('numero', $nota->numero)->where('serie', $nota->serie)->exists()) {
            $falha('duplicidade', 'Já existe outra nota deste emissor com o mesmo número e série.');
        }
    }

    private function competencia(NotaFiscal $nota, callable $falha): void
    {
        if ($nota->data_emissao === null) {
            return;
        }

        $primeiro = Carbon::createFromFormat('Y-m-d', $nota->competencia->ano_mes.'-01')->startOfDay();
        $limite = $primeiro->copy()->addMonth()->endOfMonth()->startOfDay();

        if ($nota->data_emissao->lt($primeiro) || $nota->data_emissao->gt($limite)) {
            $falha('competencia', 'A emissão ('.$nota->data_emissao->format('d/m/Y').') está fora do período da competência ('.$primeiro->format('d/m/Y').' a '.$limite->format('d/m/Y').').');
        }
    }

    private function mascara(?string $cnpj): string
    {
        return $cnpj === null || strlen($cnpj) !== 14 ? (string) $cnpj : preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
}
