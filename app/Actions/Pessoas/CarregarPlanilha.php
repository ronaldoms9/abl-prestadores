<?php

namespace App\Actions\Pessoas;

use App\Enums\ResultadoImportacao;
use App\Enums\StatusImportacao;
use App\Imports\PlanilhaPessoas;
use App\Integrations\Uau\Jobs\ProcessarImportacaoPessoasJob;
use App\Models\ImportacaoPessoa;
use App\Support\Auditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as TipoArquivo;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Recebe a planilha de pessoas (fatia 5b): lê, valida o arquivo e as linhas,
 * grava a carga com uma linha por linha útil e despacha o processamento na
 * fila uau. Nada é gravado se o arquivo for recusado (D5b.1).
 */
class CarregarPlanilha
{
    public const COLUNAS = ['cpf', 'cnpj'];

    private const TIPOS = ['csv' => TipoArquivo::CSV, 'xlsx' => TipoArquivo::XLSX];

    /**
     * @throws PlanilhaInvalida
     */
    public function executar(UploadedFile $arquivo): ImportacaoPessoa
    {
        $extensao = Str::lower($arquivo->getClientOriginalExtension());
        $linhas = $this->ler($arquivo, $extensao);

        $disco = config('uau.planilha.disco');
        $caminho = $arquivo->storeAs('importacoes-pessoas', Str::uuid().'.'.$extensao, $disco);

        try {
            $importacao = DB::transaction(function () use ($arquivo, $caminho, $linhas) {
                $importacao = ImportacaoPessoa::create([
                    'arquivo_path' => $caminho,
                    'nome_original' => Str::limit($arquivo->getClientOriginalName(), 250, ''),
                    'status' => StatusImportacao::Pendente,
                    'total_linhas' => $linhas->count(),
                ]);

                $this->gravarLinhas($importacao, $linhas);

                Auditoria::registrar('planilha-carregada', $importacao, [
                    'arquivo' => $importacao->nome_original,
                    'linhas' => $linhas->count(),
                ]);

                return $importacao;
            });
        } catch (Throwable $e) {
            Storage::disk($disco)->delete($caminho);

            throw $e;
        }

        ProcessarImportacaoPessoasJob::dispatch($importacao->id);

        return $importacao;
    }

    /**
     * @return Collection<int, LinhaPlanilha>
     */
    private function ler(UploadedFile $arquivo, string $extensao): Collection
    {
        if (! isset(self::TIPOS[$extensao])) {
            throw new PlanilhaInvalida('Envie um arquivo .xlsx ou .csv.');
        }

        $leitor = new PlanilhaPessoas();

        try {
            Excel::import($leitor, $arquivo, readerType: self::TIPOS[$extensao]);
        } catch (Throwable $e) {
            report($e);

            throw new PlanilhaInvalida('Não foi possível ler o arquivo. Confira se é um .xlsx ou .csv válido.');
        }

        $faltando = array_diff(self::COLUNAS, $leitor->cabecalho());
        if ($faltando !== []) {
            throw new PlanilhaInvalida('A primeira linha precisa ter as colunas cpf e cnpj. Faltando: '.implode(', ', $faltando).'.');
        }

        $linhas = collect($leitor->dados())
            ->map(fn (array $celulas, int $numero) => LinhaPlanilha::deCelulas($numero, $celulas))
            ->filter()
            ->values();

        if ($linhas->isEmpty()) {
            throw new PlanilhaInvalida('A planilha não tem nenhuma linha preenchida.');
        }

        $limite = (int) config('uau.planilha.max_linhas');
        if ($linhas->count() > $limite) {
            throw new PlanilhaInvalida("A planilha tem {$linhas->count()} linhas preenchidas; o limite é {$limite}. Divida o arquivo.");
        }

        return $linhas;
    }

    /** @param  Collection<int, LinhaPlanilha>  $linhas */
    private function gravarLinhas(ImportacaoPessoa $importacao, Collection $linhas): void
    {
        $agora = now();

        $linhas->map(fn (LinhaPlanilha $linha) => [
            'importacao_pessoa_id' => $importacao->id,
            'linha' => $linha->linha,
            'cpf' => $linha->cpf,
            'cnpj' => $linha->cnpj,
            // Documento inválido já sai com o resultado: o job não o consulta no UAU.
            'resultado_pf' => $linha->cpf !== null && ! $linha->cpfValido ? ResultadoImportacao::Invalida->value : null,
            'resultado_pj' => $linha->cnpj !== null && ! $linha->cnpjValido ? ResultadoImportacao::Invalida->value : null,
            'mensagem' => $linha->mensagem(),
            'created_at' => $agora,
            'updated_at' => $agora,
        ])->chunk(500)->each(fn (Collection $bloco) => $importacao->linhas()->insert($bloco->values()->all()));
    }
}
