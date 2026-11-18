<?php

namespace App\Actions\Notas;

use App\Enums\OrigemDadosNota;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Enums\TributoNota;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Models\User;
use App\Services\NotaFiscal\LeitorXmlNfse;
use App\Services\NotaFiscal\NotaLida;
use App\Services\NotaFiscal\XmlNaoLido;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registra o envio da nota de um fechamento: guarda os arquivos, lê o XML (ou
 * usa os campos digitados), grava a nota e roda a conferência.
 */
class EnviarNota
{
    public function __construct(private readonly LeitorXmlNfse $leitor, private readonly ConferirNota $conferir)
    {
    }

    /**
     * @param  array<string, mixed>  $manual  numero, serie, chave_acesso, data_emissao, cnpj_emissor, cnpj_tomador, valor_bruto, retencoes[TRIBUTO]
     */
    public function executar(Fechamento $fechamento, ?UploadedFile $xml, ?UploadedFile $pdf, array $manual, ?User $enviadaPor): NotaFiscal
    {
        if ($xml === null && $pdf === null) {
            throw ValidationException::withMessages(['arquivo' => 'Envie o PDF ou o XML da nota.']);
        }

        // A leitura do XML (que pode recusar o envio) fica fora da transação e antes de gravar qualquer arquivo.
        $dados = $this->dados($xml, $manual);
        $antigos = [];
        $novos = [];

        try {
            $nota = DB::transaction(function () use ($fechamento, $xml, $pdf, $dados, $enviadaPor, &$antigos, &$novos) {
                // Trava o fechamento: dois envios ao mesmo tempo (duplo clique, portal e tela interna) não criam duas notas.
                $fechamento = Fechamento::query()->with('competencia')->lockForUpdate()->findOrFail($fechamento->id);

                if ($fechamento->status !== StatusFechamento::AguardandoNota) {
                    throw ValidationException::withMessages(['fechamento' => 'Este fechamento ainda não foi aprovado (ou já seguiu adiante); a nota não pode ser enviada.']);
                }

                $existente = NotaFiscal::query()->where('fechamento_id', $fechamento->id)->lockForUpdate()->first();
                if ($existente !== null && ! $existente->status->aceitaEnvio()) {
                    throw ValidationException::withMessages(['fechamento' => 'Já há uma nota '.mb_strtolower($existente->status->rotulo()).' para este fechamento.']);
                }

                $this->recusarDuplicidadeDeChave($dados['chave_acesso'], $existente?->id);

                $caminhos = $this->guardar($fechamento, $xml, $pdf);
                $novos = array_filter($caminhos);
                $antigos = $existente === null ? [] : array_filter([$existente->xml_path, $existente->pdf_path]);

                $nota = $existente ?? new NotaFiscal(['fechamento_id' => $fechamento->id, 'colaborador_id' => $fechamento->colaborador_id, 'competencia_id' => $fechamento->competencia_id]);
                $nota->fill($dados['nota'] + $caminhos + [
                    'status' => StatusNota::Enviada,
                    'motivo_recusa' => null,
                    'divergencias' => null,
                    'justificativa_aprovacao' => null,
                    'decidido_por' => null,
                    'decidido_em' => null,
                    'enviada_por' => $enviadaPor?->id,
                ])->save();

                $nota->retencoes()->delete();
                foreach ($dados['retencoes'] as $tributo => $centavos) {
                    $nota->retencoes()->create(['tributo' => $tributo, 'valor' => Dinheiro::decimal($centavos)]);
                }

                Auditoria::registrar('nota-enviada', $nota, ['fechamento_id' => $fechamento->id, 'origem' => $nota->origem_dados->value, 'reenvio' => $existente !== null]);

                return $nota;
            });
        } catch (Throwable $e) {
            // Falhou depois de guardar os arquivos: não deixa lixo no disco.
            Storage::disk(config('cadastros.notas.disco'))->delete(array_values($novos));

            throw $e;
        }

        // Reenvio: os arquivos da versão recusada saem do disco.
        Storage::disk(config('cadastros.notas.disco'))->delete(array_values(array_diff($antigos, $novos)));

        $this->conferir->executar($nota);

        return $nota->refresh();
    }

    /** Lê o XML (se houver e der) ou usa os campos digitados. @return array{nota: array<string, mixed>, retencoes: array<string, int>, chave_acesso: ?string} */
    private function dados(?UploadedFile $xml, array $manual): array
    {
        $lida = null;

        if ($xml !== null) {
            try {
                $lida = $this->leitor->ler((string) file_get_contents($xml->getRealPath()));
            } catch (XmlNaoLido $e) {
                if (! $this->temCamposManuais($manual)) {
                    throw ValidationException::withMessages(['xml' => $e->getMessage()]);
                }
            }
        }

        return $lida !== null ? $this->deLida($lida) : $this->deManual($manual);
    }

    /** @return array{nota: array<string, mixed>, retencoes: array<string, int>, chave_acesso: ?string} */
    private function deLida(NotaLida $n): array
    {
        return [
            'nota' => [
                'numero' => $n->numero, 'serie' => $n->serie, 'chave_acesso' => $n->chaveAcesso, 'data_emissao' => $n->emissao,
                'cnpj_emissor' => $n->cnpjEmissor, 'cnpj_tomador' => $n->cnpjTomador,
                'valor_bruto' => $n->valorBruto === null ? null : Dinheiro::decimal($n->valorBruto),
                'valor_retencoes' => Dinheiro::decimal($n->totalRetencoes()),
                'valor_liquido' => $n->valorLiquido === null ? null : Dinheiro::decimal($n->valorLiquido),
                'origem_dados' => OrigemDadosNota::Xml,
            ],
            'retencoes' => $n->retencoes,
            'chave_acesso' => $n->chaveAcesso,
        ];
    }

    /** @return array{nota: array<string, mixed>, retencoes: array<string, int>, chave_acesso: ?string} */
    private function deManual(array $m): array
    {
        $digitos = fn ($v) => blank($v) ? null : preg_replace('/\D+/', '', (string) $v);
        $retencoes = [];
        foreach ((array) ($m['retencoes'] ?? []) as $tributo => $valor) {
            if (TributoNota::tryFrom((string) $tributo) !== null && filled($valor) && Dinheiro::centavos($valor) > 0) {
                $retencoes[(string) $tributo] = Dinheiro::centavos($valor);
            }
        }

        $bruto = blank($m['valor_bruto'] ?? null) ? null : Dinheiro::centavos($m['valor_bruto']);
        $chave = $digitos($m['chave_acesso'] ?? null);

        return [
            'nota' => [
                'numero' => blank($m['numero'] ?? null) ? null : trim((string) $m['numero']),
                'serie' => blank($m['serie'] ?? null) ? null : trim((string) $m['serie']),
                'chave_acesso' => $chave,
                'data_emissao' => blank($m['data_emissao'] ?? null) ? null : Carbon::parse($m['data_emissao'])->startOfDay(),
                'cnpj_emissor' => $digitos($m['cnpj_emissor'] ?? null),
                'cnpj_tomador' => $digitos($m['cnpj_tomador'] ?? null),
                'valor_bruto' => $bruto === null ? null : Dinheiro::decimal($bruto),
                'valor_retencoes' => Dinheiro::decimal(array_sum($retencoes)),
                'valor_liquido' => $bruto === null ? null : Dinheiro::decimal($bruto - array_sum($retencoes)),
                'origem_dados' => OrigemDadosNota::Manual,
            ],
            'retencoes' => $retencoes,
            'chave_acesso' => $chave,
        ];
    }

    private function temCamposManuais(array $m): bool
    {
        return filled($m['numero'] ?? null) && filled($m['valor_bruto'] ?? null);
    }

    /** @return array{xml_path: ?string, pdf_path: ?string} */
    private function guardar(Fechamento $fechamento, ?UploadedFile $xml, ?UploadedFile $pdf): array
    {
        $disco = Storage::disk(config('cadastros.notas.disco'));
        $pasta = "notas/{$fechamento->competencia->ano_mes}/{$fechamento->colaborador_id}";

        $salvar = fn (?UploadedFile $arquivo, string $extensao) => $arquivo === null ? null : $disco->putFileAs($pasta, $arquivo, Str::uuid().'.'.$extensao);

        return ['xml_path' => $salvar($xml, 'xml'), 'pdf_path' => $salvar($pdf, 'pdf')];
    }

    private function recusarDuplicidadeDeChave(?string $chave, ?int $ignorarId): void
    {
        if ($chave !== null && NotaFiscal::query()->where('chave_acesso', $chave)->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))->exists()) {
            throw ValidationException::withMessages(['chave_acesso' => 'Já existe outra nota com esta chave de acesso.']);
        }
    }
}
