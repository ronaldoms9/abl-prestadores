<?php

namespace App\Support\Admissao;

use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Enums\AplicaDocumento;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\Remuneracao;
use Carbon\CarbonInterface;

/**
 * Itens do checklist que o sistema marca sozinho, cada um por uma chave. A
 * chave liga o item configurado à regra daqui; item sem chave é manual.
 * A avaliação não grava nada: quem precisa gravar usa SincronizarItensAutomaticos.
 */
class ItensAutomaticos
{
    public const PJ_NO_UAU = 'pj_no_uau';

    public const REMUNERACAO_DEFINIDA = 'remuneracao_definida';

    public const DOCUMENTOS_OBRIGATORIOS = 'documentos_obrigatorios';

    public const QUALIFICACOES_EM_DIA = 'qualificacoes_em_dia';

    public const CONTRATO_ASSINADO = 'contrato_assinado';

    /** chave => [nome padrão, ordem padrão] */
    public const PADRAO = [
        self::PJ_NO_UAU => ['PJ gravada no UAU', 10],
        self::REMUNERACAO_DEFINIDA => ['Remuneração definida', 20],
        self::DOCUMENTOS_OBRIGATORIOS => ['Documentos obrigatórios enviados', 30],
        self::QUALIFICACOES_EM_DIA => ['Qualificações obrigatórias do cargo em dia', 40],
        self::CONTRATO_ASSINADO => ['Contrato assinado anexado', 50],
    ];

    public function __construct(private readonly SituacaoQualificacoes $qualificacoes)
    {
    }

    public static function chaveValida(string $chave): bool
    {
        return array_key_exists($chave, self::PADRAO);
    }

    /**
     * @return array<string, array{ok: bool, detalhe: ?string}>
     */
    public function avaliar(Colaborador $colaborador, CarbonInterface $hoje): array
    {
        $c = Colaborador::query()
            ->with(['pessoaJuridica', 'cargo.exigencias' => fn ($q) => $q->where('obrigatoria', true), 'cargo.exigencias.tipo', 'qualificacoes.tipo'])
            ->findOrFail($colaborador->id);

        return [
            self::PJ_NO_UAU => $this->pjNoUau($c),
            self::REMUNERACAO_DEFINIDA => $this->remuneracao($c),
            self::DOCUMENTOS_OBRIGATORIOS => $this->documentos($c, $hoje),
            self::QUALIFICACOES_EM_DIA => $this->qualificacoesEmDia($c, $hoje),
            self::CONTRATO_ASSINADO => $this->contratoAssinado($c),
        ];
    }

    /** @return array{ok: bool, detalhe: ?string} */
    private function pjNoUau(Colaborador $c): array
    {
        $ok = $c->pessoaJuridica->uau_codigo !== null;

        return ['ok' => $ok, 'detalhe' => $ok ? null : 'A pessoa jurídica ainda não tem código no UAU.'];
    }

    /** @return array{ok: bool, detalhe: ?string} */
    private function remuneracao(Colaborador $c): array
    {
        $ok = Remuneracao::query()->where('colaborador_id', $c->id)->exists();

        return ['ok' => $ok, 'detalhe' => $ok ? null : 'Defina a remuneração do colaborador.'];
    }

    /** @return array{ok: bool, detalhe: ?string} */
    private function documentos(Colaborador $c, CarbonInterface $hoje): array
    {
        $tipos = DocumentoTipo::query()->ativos()->where('obrigatorio_na_admissao', true)->orderBy('nome')->get();
        if ($tipos->isEmpty()) {
            return ['ok' => true, 'detalhe' => null];
        }

        $documentos = Documento::query()
            ->whereIn('documento_tipo_id', $tipos->pluck('id'))
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('documentavel_type', 'colaborador')->where('documentavel_id', $c->id))
                ->orWhere(fn ($w) => $w->where('documentavel_type', 'pessoa-fisica')->where('documentavel_id', $c->pessoa_fisica_id))
                ->orWhere(fn ($w) => $w->where('documentavel_type', 'pessoa-juridica')->where('documentavel_id', $c->pessoa_juridica_id)))
            ->get();

        $dia = $hoje->copy()->startOfDay();
        $pendentes = $tipos->filter(function (DocumentoTipo $tipo) use ($documentos, $dia) {
            return ! $documentos
                ->filter(fn (Documento $d) => $d->documento_tipo_id === $tipo->id && $this->donoServe($tipo, $d))
                // Com validade, o documento tem que estar válido hoje (a validade é o último dia de uso).
                ->contains(fn (Documento $d) => $d->validade === null || $d->validade->gte($dia));
        });

        return [
            'ok' => $pendentes->isEmpty(),
            'detalhe' => $pendentes->isEmpty() ? null : 'Faltam ou estão vencidos: '.$pendentes->pluck('nome')->implode(', ').'.',
        ];
    }

    /** O tipo "colaborador" só vale se anexado ao colaborador, e assim por diante. "Todos" aceita qualquer dono. */
    private function donoServe(DocumentoTipo $tipo, Documento $documento): bool
    {
        return match ($tipo->aplica_a) {
            AplicaDocumento::Todos => true,
            AplicaDocumento::Colaborador => $documento->documentavel_type === 'colaborador',
            AplicaDocumento::PessoaFisica => $documento->documentavel_type === 'pessoa-fisica',
            AplicaDocumento::PessoaJuridica => $documento->documentavel_type === 'pessoa-juridica',
        };
    }

    /** @return array{ok: bool, detalhe: ?string} */
    private function qualificacoesEmDia(Colaborador $c, CarbonInterface $hoje): array
    {
        $situacao = $this->qualificacoes->situacao($c, $hoje->copy()->startOfDay(), 0);
        $pendencias = $situacao['ausentes']->pluck('nome')
            ->merge($situacao['vencidas']->map(fn ($q) => $q->tipo->nome.' (vencida)'));

        return [
            'ok' => $pendencias->isEmpty(),
            'detalhe' => $pendencias->isEmpty() ? null : 'Pendentes: '.$pendencias->implode(', ').'.',
        ];
    }

    /** @return array{ok: bool, detalhe: ?string} */
    private function contratoAssinado(Colaborador $c): array
    {
        $ok = Contrato::query()
            ->where('colaborador_id', $c->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->where('status', StatusContrato::Assinado->value)
            ->exists();

        return ['ok' => $ok, 'detalhe' => $ok ? null : 'Gere o contrato e anexe o arquivo assinado.'];
    }
}
