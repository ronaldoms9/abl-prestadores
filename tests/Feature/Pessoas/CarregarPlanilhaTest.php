<?php

namespace Tests\Feature\Pessoas;

use App\Actions\Pessoas\CarregarPlanilha;
use App\Actions\Pessoas\PlanilhaInvalida;
use App\Actions\Uau\ImportarPessoa;
use App\Enums\ResultadoImportacao;
use App\Enums\StatusImportacao;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\Jobs\ProcessarImportacaoPessoasJob;
use App\Integrations\Uau\OperacaoUau;
use App\Models\ImportacaoPessoa;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\VinculoSugerido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as TipoArquivo;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class CarregarPlanilhaTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    public function test_cabecalho_sem_cnpj_recusa_o_arquivo_sem_gravar_nada(): void
    {
        $this->assertRecusada($this->csv("cpf;nome\n52998224725;Mariana"), 'Faltando: cnpj.');
    }

    public function test_arquivo_vazio_e_acima_do_limite_sao_recusados(): void
    {
        $this->assertRecusada($this->csv("cpf;cnpj\n;\n"), 'nenhuma linha preenchida');

        config(['uau.planilha.max_linhas' => 2]);
        $this->assertRecusada($this->csv("cpf;cnpj\n52998224725;\n74620153826;\n11144477735;"), 'o limite é 2');
    }

    public function test_extensao_e_arquivo_ilegivel_sao_recusados(): void
    {
        $this->assertRecusada(UploadedFile::fake()->createWithContent('pessoas.txt', "cpf;cnpj\n"), '.xlsx ou .csv');
        $this->assertRecusada(UploadedFile::fake()->createWithContent('pessoas.xlsx', 'não é um xlsx'), 'Não foi possível ler');
    }

    public function test_csv_com_ponto_e_virgula_ou_virgula_e_xlsx_chegam_as_mesmas_linhas(): void
    {
        Queue::fake();
        $linhas = [['529.982.247-25', ''], ['', '27543198000106'], ['', '']];

        $csv = $this->carregar($this->csv("CPF;CNPJ\n529.982.247-25;\n;27543198000106\n;\n"));
        $virgula = $this->carregar($this->csv("cpf,cnpj\n529.982.247-25,\n,27543198000106\n"));
        $xlsx = $this->carregar($this->xlsx($linhas));

        foreach ([$csv, $virgula, $xlsx] as $importacao) {
            $this->assertSame(2, $importacao->total_linhas);
            $this->assertSame(
                [[2, '52998224725', null], [3, null, '27543198000106']],
                $importacao->linhas()->orderBy('linha')->get()->map(fn ($l) => [$l->linha, $l->cpf, $l->cnpj])->all(),
            );
            Storage::disk('local')->assertExists($importacao->arquivo_path);
        }
    }

    public function test_cada_resultado_e_o_vinculo_sugerido(): void
    {
        $uau = $this->simularUau();

        $importacao = $this->carregar($this->csv(implode("\n", [
            'cpf;cnpj',
            '52998224725;27543198000106',
            '74620153826;',
            '11144477735;27543198000106',
            '52998224726;',
        ])));

        $linhas = $importacao->fresh()->linhas()->orderBy('linha')->get()->keyBy('linha');
        $this->assertSame(StatusImportacao::Concluida, $importacao->fresh()->status);

        $this->assertSame(ResultadoImportacao::Importada, $linhas[2]->resultado_pf);
        $this->assertSame(ResultadoImportacao::Importada, $linhas[2]->resultado_pj);
        $this->assertSame(ResultadoImportacao::Inativa, $linhas[3]->resultado_pf);
        $this->assertSame(ResultadoImportacao::NaoEncontrada, $linhas[4]->resultado_pf);
        $this->assertSame(ResultadoImportacao::JaExistia, $linhas[4]->resultado_pj);
        $this->assertSame(ResultadoImportacao::Invalida, $linhas[5]->resultado_pf);
        $this->assertSame('CPF com dígito verificador inválido.', $linhas[5]->mensagem);

        // O inválido não chega ao UAU.
        $consultados = array_map(fn ($c) => $c['argumentos']['cpf_cnpj'], $uau->chamadas(OperacaoUau::PessoaPorDocumento));
        $this->assertNotContains('52998224726', $consultados);

        // Só a linha com as duas pessoas encontradas sugere o vínculo.
        $this->assertSame(1, VinculoSugerido::count());
        $vinculo = VinculoSugerido::first();
        $this->assertSame(PessoaFisica::where('cpf', '52998224725')->value('id'), $vinculo->pessoa_fisica_id);
        $this->assertSame(PessoaJuridica::where('cnpj', '27543198000106')->value('id'), $vinculo->pessoa_juridica_id);
        $this->assertSame($importacao->id, $vinculo->importacao_pessoa_id);

        $resumo = $importacao->fresh()->resumo;
        $this->assertSame(['importada' => 1, 'inativa' => 1, 'invalida' => 1, 'nao_encontrada' => 1], collect($resumo['pf'])->sortKeys()->all());
        $this->assertSame(1, $resumo['vinculos']);
        $this->assertSame(0, $resumo['nao_processadas']);
    }

    public function test_recarregar_a_mesma_planilha_nao_duplica(): void
    {
        $this->simularUau();
        $conteudo = "cpf;cnpj\n52998224725;27543198000106\n";

        $this->carregar($this->csv($conteudo));
        $segunda = $this->carregar($this->csv($conteudo));

        $this->assertSame(1, PessoaFisica::count());
        $this->assertSame(1, PessoaJuridica::count());
        $this->assertSame(1, VinculoSugerido::count());
        $linha = $segunda->linhas()->first();
        $this->assertSame(ResultadoImportacao::JaExistia, $linha->resultado_pf);
        $this->assertSame(ResultadoImportacao::JaExistia, $linha->resultado_pj);
    }

    public function test_vinculo_descartado_nao_volta(): void
    {
        $this->simularUau();
        $conteudo = "cpf;cnpj\n52998224725;27543198000106\n";

        $this->carregar($this->csv($conteudo));
        VinculoSugerido::first()->delete();
        $this->carregar($this->csv($conteudo));

        $this->assertSame(0, VinculoSugerido::count());
        $this->assertSame(1, VinculoSugerido::withTrashed()->count());
    }

    public function test_recusa_do_uau_fica_na_linha_e_a_carga_segue(): void
    {
        $outraPessoa = $this->dadosUau('pessoas/74620153826.json');
        $outraPessoa['cpfCnpj'] = '52998224725';
        $this->simularUau(['pessoas/74620153826.json' => $outraPessoa]);

        $importacao = $this->carregar($this->csv("cpf;cnpj\n74620153826;\n52998224725;\n"));

        $linhas = $importacao->linhas()->orderBy('linha')->get();
        $this->assertSame(ResultadoImportacao::Recusada, $linhas[0]->resultado_pf);
        $this->assertStringContainsString('CPF: O UAU devolveu a resposta de outra pessoa.', $linhas[0]->mensagem);
        $this->assertSame(ResultadoImportacao::Importada, $linhas[1]->resultado_pf);
        $this->assertSame(StatusImportacao::Concluida, $importacao->fresh()->status);
    }

    public function test_processa_em_lotes_ate_concluir(): void
    {
        $this->simularUau();
        config(['uau.planilha.lote' => 2]);
        Queue::fake();

        $importacao = $this->carregar($this->csv("cpf;cnpj\n52998224725;\n74620153826;\n11144477735;\n03174682940;\n;27543198000106\n"));

        $execucoes = 0;
        do {
            $this->rodarJob($importacao);
            $execucoes++;
        } while ($importacao->fresh()->emAndamento() && $execucoes < 10);

        $this->assertSame(3, $execucoes);
        $this->assertSame(StatusImportacao::Concluida, $importacao->fresh()->status);
        $this->assertNotNull($importacao->fresh()->iniciada_em);
        $this->assertSame(0, $importacao->linhas()->pendentes()->count());
        Queue::assertPushedOn('uau', ProcessarImportacaoPessoasJob::class);
    }

    public function test_queda_do_uau_no_meio_retoma_sem_consultar_de_novo(): void
    {
        $uau = $this->simularUau();
        config(['uau.planilha.lote' => 10]);
        Queue::fake();

        $importacao = $this->carregar($this->csv("cpf;cnpj\n52998224725;27543198000106\n74620153826;\n"));

        // CPF da primeira linha já consultado; a próxima consulta (o CNPJ) cai.
        $this->processarPessoa('52998224725');
        $uau->falharEm(OperacaoUau::PessoaPorDocumento, new UauIndisponivel('UAU fora do ar.', 503), 1);

        try {
            $this->rodarJob($importacao);
            $this->fail('Deveria ter relançado UauIndisponivel.');
        } catch (UauIndisponivel) {
        }

        $this->rodarJob($importacao);

        $consultas = array_count_values(array_map(fn ($c) => $c['argumentos']['cpf_cnpj'], $uau->chamadas(OperacaoUau::PessoaPorDocumento)));
        $this->assertSame(1, $consultas['52998224725']);
        $this->assertSame(2, $consultas['27543198000106']);
        $this->assertSame(1, $consultas['74620153826']);
        $this->assertSame(StatusImportacao::Concluida, $importacao->fresh()->status);
    }

    public function test_tentativas_esgotadas_marcam_a_carga_como_falhou(): void
    {
        $this->simularUau();
        Queue::fake();
        $importacao = $this->carregar($this->csv("cpf;cnpj\n52998224725;\n74620153826;\n"));

        (new ProcessarImportacaoPessoasJob($importacao->id))->failed(new UauIndisponivel('UAU fora do ar.', 503));

        $importacao->refresh();
        $this->assertSame(StatusImportacao::Falhou, $importacao->status);
        $this->assertSame('UAU fora do ar.', $importacao->erro);
        $this->assertSame(2, $importacao->resumo['nao_processadas']);
    }

    public function test_upload_registra_a_auditoria(): void
    {
        Queue::fake();

        $importacao = $this->carregar($this->csv("cpf;cnpj\n52998224725;\n"));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'planilha-carregada',
            'subject_type' => 'importacao-pessoa',
            'subject_id' => $importacao->id,
        ]);
        Queue::assertPushed(ProcessarImportacaoPessoasJob::class, fn ($job) => $job->importacaoId === $importacao->id);
    }

    private function carregar(UploadedFile $arquivo): ImportacaoPessoa
    {
        return app(CarregarPlanilha::class)->executar($arquivo);
    }

    private function rodarJob(ImportacaoPessoa $importacao): void
    {
        $job = (new ProcessarImportacaoPessoasJob($importacao->id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);
    }

    /** Consulta adiantada, como se um lote anterior tivesse gravado só o CPF da linha. */
    private function processarPessoa(string $cpf): void
    {
        $linha = ImportacaoPessoa::latest('id')->first()->linhas()->where('cpf', $cpf)->first();
        $linha->update(['resultado_pf' => app(ImportarPessoa::class)->executar($cpf)->resultado]);
    }

    private function assertRecusada(UploadedFile $arquivo, string $mensagem): void
    {
        try {
            $this->carregar($arquivo);
            $this->fail('A planilha deveria ter sido recusada.');
        } catch (PlanilhaInvalida $e) {
            $this->assertStringContainsString($mensagem, $e->getMessage());
        }

        $this->assertSame(0, ImportacaoPessoa::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function csv(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pessoas.csv', $conteudo);
    }

    /** @param  list<array{0: mixed, 1: mixed}>  $linhas */
    private function xlsx(array $linhas): UploadedFile
    {
        $planilha = new class($linhas) implements FromArray
        {
            public function __construct(private array $linhas)
            {
            }

            public function array(): array
            {
                return [['cpf', 'cnpj'], ...$this->linhas];
            }
        };

        return UploadedFile::fake()->createWithContent('pessoas.xlsx', Excel::raw($planilha, TipoArquivo::XLSX));
    }
}
