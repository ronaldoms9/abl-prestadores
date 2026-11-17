<?php

namespace App\Actions\Contratos;

use App\Enums\MotivoAditivo;
use App\Enums\TipoContrato;
use App\Models\ContratoModelo;
use App\Support\Auditoria;
use App\Support\Contratos\RenderizadorContrato;
use App\Support\Contratos\SanitizadorHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou altera um modelo. O corpo é limpo (sem script nem atributos de
 * evento) e só pode ter variáveis do catálogo: uma desconhecida bloqueia o
 * salvamento. Mudar nome ou corpo de um modelo que já gerou contratos cria uma
 * nova versão (versao + 1) e desativa a anterior; os contratos continuam
 * apontando para a versão com que foram gerados. O tipo de um modelo em uso
 * não muda.
 */
class SalvarContratoModelo
{
    public function __construct(private readonly RenderizadorContrato $renderizador)
    {
    }

    /** @param  array{nome: string, tipo: string, corpo_html: string, motivo?: string|null, ativo?: bool|null}  $dados */
    public function executar(?ContratoModelo $modelo, array $dados): ContratoModelo
    {
        $nome = trim($dados['nome']);
        $tipo = TipoContrato::from($dados['tipo']);
        // O motivo só faz sentido no aditivo.
        $motivo = $tipo === TipoContrato::Aditivo && ! empty($dados['motivo']) ? MotivoAditivo::from($dados['motivo']) : null;
        $corpo = SanitizadorHtml::limpar($dados['corpo_html']);

        if ($corpo === '') {
            throw ValidationException::withMessages(['corpo_html' => 'O corpo do modelo não pode ficar vazio.']);
        }

        $desconhecidas = $this->renderizador->desconhecidas($corpo);
        if ($desconhecidas !== []) {
            throw ValidationException::withMessages(['corpo_html' => 'Variável desconhecida no modelo: '.implode(', ', array_map(fn ($v) => '{{'.$v.'}}', $desconhecidas)).'. Use só as variáveis do painel.']);
        }

        return DB::transaction(function () use ($modelo, $nome, $tipo, $motivo, $corpo, $dados) {
            $this->exigirNomeLivre($modelo, $nome, $tipo);

            if ($modelo === null) {
                return ContratoModelo::create(['nome' => $nome, 'tipo' => $tipo, 'motivo' => $motivo, 'corpo_html' => $corpo, 'versao' => 1, 'ativo' => $dados['ativo'] ?? true]);
            }

            $emUso = $modelo->contratos()->exists();

            if ($emUso && $modelo->tipo !== $tipo) {
                throw ValidationException::withMessages(['tipo' => 'O tipo de um modelo que já gerou contratos não pode mudar. Crie outro modelo.']);
            }

            $mudouTexto = $modelo->nome !== $nome || $modelo->corpo_html !== $corpo || $modelo->motivo !== $motivo;

            if ($emUso && $mudouTexto) {
                $nova = ContratoModelo::create([
                    'nome' => $nome, 'tipo' => $tipo, 'motivo' => $motivo, 'corpo_html' => $corpo,
                    'versao' => $this->proximaVersao($modelo), 'modelo_anterior_id' => $modelo->id,
                    'ativo' => $dados['ativo'] ?? $modelo->ativo,
                ]);
                $modelo->update(['ativo' => false]);

                Auditoria::registrar('contrato-modelo-versionado', $nova, ['versao' => $nova->versao, 'anterior' => $modelo->id]);

                return $nova;
            }

            $modelo->update(['nome' => $nome, 'tipo' => $tipo, 'motivo' => $motivo, 'corpo_html' => $corpo, 'ativo' => $dados['ativo'] ?? $modelo->ativo]);

            return $modelo;
        });
    }

    private function proximaVersao(ContratoModelo $modelo): int
    {
        $versao = $modelo->versao;
        $atual = $modelo;
        while ($atual->modelo_anterior_id !== null) {
            $atual = ContratoModelo::withTrashed()->find($atual->modelo_anterior_id);
            if ($atual === null) {
                break;
            }
            $versao = max($versao, $atual->versao);
        }

        return $versao + 1;
    }

    private function exigirNomeLivre(?ContratoModelo $modelo, string $nome, TipoContrato $tipo): void
    {
        $existe = ContratoModelo::query()->ativos()
            ->where('tipo', $tipo->value)
            ->where('nome', $nome)
            ->when($modelo, fn ($q) => $q->where('id', '!=', $modelo->id))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages(['nome' => 'Já existe um modelo ativo de '.mb_strtolower($tipo->rotulo()).' com este nome.']);
        }
    }
}
