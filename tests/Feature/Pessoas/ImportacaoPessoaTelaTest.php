<?php

namespace Tests\Feature\Pessoas;

use App\Enums\ResultadoImportacao;
use App\Enums\StatusImportacao;
use App\Http\Controllers\Pessoas\ImportacaoPessoaController;
use App\Imports\PlanilhaPessoas;
use App\Models\ImportacaoPessoa;
use App\Models\ImportacaoPessoaLinha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as TipoArquivo;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportacaoPessoaTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->usuario = $this->usuarioCom('pessoa-importar');
    }

    public function test_upload_valido_cria_a_carga_e_mostra_o_resultado(): void
    {
        Queue::fake();
        $arquivo = UploadedFile::fake()->createWithContent('prestadores.csv', "cpf;cnpj\n52998224725;\n");

        $resposta = $this->actingAs($this->usuario)->post(route('importacao-pessoa.store'), ['arquivo' => $arquivo]);

        $importacao = ImportacaoPessoa::sole();
        $resposta->assertRedirect(route('importacao-pessoa.show', $importacao->id));
        $this->assertSame($this->usuario->id, $importacao->created_by);
        $this->assertDatabaseHas('activity_log', ['event' => 'planilha-carregada', 'causer_id' => $this->usuario->id]);

        $this->get(route('importacao-pessoa.show', $importacao->id))
            ->assertOk()
            ->assertSee('prestadores.csv')
            ->assertSee('Processando: 0 de 1 linhas');
    }

    public function test_cabecalho_errado_volta_com_o_erro_no_campo(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('prestadores.csv', "documento\n52998224725\n");

        $this->actingAs($this->usuario)
            ->from(route('importacao-pessoa.index'))
            ->post(route('importacao-pessoa.store'), ['arquivo' => $arquivo])
            ->assertRedirect(route('importacao-pessoa.index'))
            ->assertSessionHasErrors(['arquivo' => 'A primeira linha precisa ter as colunas cpf e cnpj. Faltando: cpf, cnpj.']);

        $this->assertSame(0, ImportacaoPessoa::count());
    }

    public function test_extensao_errada_e_recusada_pela_validacao(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('importacao-pessoa.store'), ['arquivo' => UploadedFile::fake()->create('prestadores.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['arquivo' => 'Envie um arquivo .xlsx ou .csv.']);
    }

    public function test_modelo_baixa_um_xlsx_com_o_cabecalho(): void
    {
        $resposta = $this->actingAs($this->usuario)->get(route('importacao-pessoa.modelo'));

        $resposta->assertOk()->assertDownload('modelo-pessoas.xlsx');
        $linhas = Excel::toArray(new PlanilhaPessoas(), $resposta->getFile()->getPathname(), readerType: TipoArquivo::XLSX);
        $this->assertSame(['cpf', 'cnpj'], $linhas[0][0]);
    }

    public function test_filtro_so_com_problema_esconde_as_linhas_importadas(): void
    {
        $importacao = ImportacaoPessoa::factory()->create(['status' => StatusImportacao::Concluida, 'total_linhas' => 3]);
        $this->linha($importacao, 2, ['cpf' => '52998224725', 'resultado_pf' => ResultadoImportacao::Importada]);
        $this->linha($importacao, 3, ['cpf' => '11144477735', 'resultado_pf' => ResultadoImportacao::NaoEncontrada]);
        $this->linha($importacao, 4, ['cnpj' => '27543198000106']);

        $problemas = $this->linhasDaTela($importacao, []);
        $todas = $this->linhasDaTela($importacao, ['problemas' => '0']);

        $this->assertSame([3, 4], array_column($problemas, 'linha'));
        $this->assertSame('Não processada', $problemas[1]['resultado_pj']);
        $this->assertSame('111.444.777-35', $problemas[0]['cpf']);
        $this->assertSame([2, 3, 4], array_column($todas, 'linha'));
    }

    public function test_historico_mostra_o_resumo_curto(): void
    {
        $this->usuario->update(['name' => "Joana D'Arc"]);
        ImportacaoPessoa::factory()->create([
            'status' => StatusImportacao::Concluida,
            'resumo' => ['linhas' => 4, 'pf' => ['importada' => 2, 'invalida' => 1], 'pj' => ['importada' => 1], 'vinculos' => 1, 'sem_documento' => 0, 'nao_processadas' => 0],
            'created_by' => $this->usuario->id,
        ]);

        $dados = $this->actingAs($this->usuario)
            ->getJson(route('importacao-pessoa.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');

        $this->assertSame('Importada: 3 · Inválida: 1', $dados[0]['resumo']);
        // O DataTables escapa o HTML das colunas: o apóstrofo vira &#039;.
        $this->assertSame('Joana D&#039;Arc', $dados[0]['usuario']);
        $this->assertSame('Concluída', $dados[0]['status']);
    }

    public function test_resumo_curto_conta_as_nao_processadas(): void
    {
        $this->assertSame('—', ImportacaoPessoaController::resumoCurto(['pf' => [], 'pj' => []]));
        $this->assertSame('Não processada: 2', ImportacaoPessoaController::resumoCurto(['pf' => [], 'pj' => [], 'nao_processadas' => 2]));
    }

    private function linhasDaTela(ImportacaoPessoa $importacao, array $filtros): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('importacao-pessoa.show', [$importacao->id, ...$filtros]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');
    }

    private function linha(ImportacaoPessoa $importacao, int $numero, array $atributos): void
    {
        ImportacaoPessoaLinha::create(['importacao_pessoa_id' => $importacao->id, 'linha' => $numero, ...$atributos]);
    }
}
