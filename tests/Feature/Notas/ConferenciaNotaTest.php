<?php

namespace Tests\Feature\Notas;

use App\Actions\Notas\AprovarNota;
use App\Actions\Notas\EnviarNota;
use App\Actions\Notas\RecusarNota;
use App\Enums\OrigemDadosNota;
use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Events\NotaAprovada;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\Empresa;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Notifications\NotaRecusada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ConferenciaNotaTest extends TestCase
{
    use RefreshDatabase;

    private string $emissor = '';

    private string $tomador = '';

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Fechamento aprovado (aguardando nota) de R$ 5.300,00 em 09/2026, da PJ 12.345.678/0001-95 para a empresa 45.723.174/0001-10. */
    private function fechamento(array $extra = []): Fechamento
    {
        $this->contador++;
        // O último fechamento criado define os CNPJs que manual() usa: cada um tem emissor e tomador próprios (CNPJ é único).
        $this->emissor = '1234567800'.str_pad((string) $this->contador, 4, '0', STR_PAD_LEFT);
        $this->tomador = '4572317400'.str_pad((string) $this->contador, 4, '0', STR_PAD_LEFT);
        $colaborador = Colaborador::factory()->create([
            'status' => StatusColaborador::Ativo,
            'data_admissao' => '2025-01-01',
            'pessoa_juridica_id' => PessoaJuridica::factory()->create(['cnpj' => $this->emissor]),
            'empresa_id' => Empresa::factory()->create(['cnpj' => $this->tomador]),
        ]);

        return Fechamento::factory()->create($extra + [
            'colaborador_id' => $colaborador->id,
            'competencia_id' => Competencia::where('ano_mes', '2026-09')->first()?->id ?? Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada'])->id,
            'total_liquido' => 5300,
            'status' => StatusFechamento::AguardandoNota,
        ]);
    }

    private function manual(array $extra = []): array
    {
        return $extra + ['numero' => '99', 'serie' => '1', 'data_emissao' => '2026-10-02', 'cnpj_emissor' => $this->emissor, 'cnpj_tomador' => $this->tomador, 'valor_bruto' => '5300.00'];
    }

    private function enviar(Fechamento $f, array $manual = [], ?UploadedFile $xml = null, ?UploadedFile $pdf = null, ?User $por = null): NotaFiscal
    {
        return app(EnviarNota::class)->executar($f, $xml, $pdf ?? UploadedFile::fake()->create('nota.pdf', 50, 'application/pdf'), $manual, $por);
    }

    private function regras(NotaFiscal $n): array
    {
        return array_column($n->divergencias ?? [], 'regra');
    }

    private function recusaCom(callable $acao, string $trecho): void
    {
        try {
            $acao();
            $this->fail('Não recusou');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($trecho, implode(' ', $e->validator->errors()->all()));
        }
    }

    public function test_nota_certa_fica_enviada_sem_divergencia(): void
    {
        $f = $this->fechamento();
        $quem = User::factory()->create();

        $nota = $this->enviar($f, $this->manual(['retencoes' => ['IRRF' => '79.50', 'XYZ' => '5']]), por: $quem);

        $this->assertSame(StatusNota::Enviada, $nota->status);
        $this->assertNull($nota->divergencias);
        $this->assertSame([OrigemDadosNota::Manual, $quem->id, '5300.00', '79.50', '5220.50'], [$nota->origem_dados, $nota->enviada_por, $nota->valor_bruto, $nota->valor_retencoes, $nota->valor_liquido]);
        $this->assertSame([$this->emissor, $this->tomador], [$nota->cnpj_emissor, $nota->cnpj_tomador]);
        $this->assertSame(['IRRF'], $nota->retencoes()->get()->map(fn ($r) => $r->tributo->value)->all(), 'Tributo desconhecido é ignorado');
        Storage::disk('local')->assertExists($nota->pdf_path);
        $this->assertStringStartsWith('notas/2026-09/'.$f->colaborador_id.'/', $nota->pdf_path);
        $this->assertTrue(Activity::where('event', 'nota-enviada')->exists());
    }

    public function test_le_o_xml_e_guarda_os_arquivos(): void
    {
        $f = $this->fechamento();
        $conteudo = str_replace(['12.345.678/0001-95', '45723174000110'], [$this->emissor, $this->tomador], file_get_contents(base_path('tests/Fixtures/nfse/abrasf.xml')));
        $xml = UploadedFile::fake()->createWithContent('nota.xml', $conteudo);

        $nota = $this->enviar($f, [], $xml);

        $this->assertSame([OrigemDadosNota::Xml, '1234', '5300.00', '79.50'], [$nota->origem_dados, $nota->numero, $nota->valor_bruto, $nota->valor_retencoes]);
        $this->assertSame(StatusNota::Enviada, $nota->status);
        Storage::disk('local')->assertExists($nota->xml_path);
    }

    public function test_xml_ilegivel_cai_no_manual_se_houver_campos_ou_recusa_com_a_mensagem(): void
    {
        $f = $this->fechamento();
        $ruim = fn () => UploadedFile::fake()->createWithContent('nota.xml', 'não é xml');

        $this->recusaCom(fn () => $this->enviar($f, [], $ruim()), 'não é um XML válido');
        $this->assertSame(0, NotaFiscal::count());

        $nota = $this->enviar($f, $this->manual(), $ruim());
        $this->assertSame(OrigemDadosNota::Manual, $nota->origem_dados);
    }

    public function test_exige_arquivo_e_fechamento_aprovado(): void
    {
        $f = $this->fechamento();
        $this->recusaCom(fn () => app(EnviarNota::class)->executar($f, null, null, $this->manual(), null), 'PDF ou o XML');

        $f->update(['status' => StatusFechamento::Calculado]);
        $this->recusaCom(fn () => $this->enviar($f, $this->manual()), 'ainda não foi aprovado');
    }

    public function test_cada_conferencia_aponta_sua_divergencia(): void
    {
        $this->assertSame(['emissor'], $this->regras($this->enviar($this->fechamento(), $this->manual(['cnpj_emissor' => '11222333000181']))));
        $this->assertSame(['tomador'], $this->regras($this->enviar($this->fechamento(), $this->manual(['cnpj_tomador' => '11222333000181', 'numero' => '2']))));
        $this->assertSame(['valor'], $this->regras($this->enviar($this->fechamento(), $this->manual(['valor_bruto' => '5299.99', 'numero' => '3']))));
        $this->assertSame(['competencia'], $this->regras($this->enviar($this->fechamento(), $this->manual(['data_emissao' => '2026-08-31', 'numero' => '4']))));
        $this->assertSame(['competencia'], $this->regras($this->enviar($this->fechamento(), $this->manual(['data_emissao' => '2026-11-01', 'numero' => '5']))));
        $this->assertSame(['dados'], $this->regras($this->enviar($this->fechamento(), ['numero' => '6', 'valor_bruto' => '5300'])));

        $ok = $this->enviar($this->fechamento(), $this->manual(['data_emissao' => '2026-10-30', 'numero' => '7']));
        $this->assertSame(StatusNota::Enviada, $ok->status, 'Emitir no mês seguinte é normal');
    }

    public function test_tolerancia_de_valor_por_configuracao(): void
    {
        Configuracao::definir('notas.tolerancia_valor', '1.00');

        $this->assertSame(StatusNota::Enviada, $this->enviar($this->fechamento(), $this->manual(['valor_bruto' => '5299.50']))->status);
        $this->assertSame(['valor'], $this->regras($this->enviar($this->fechamento(), $this->manual(['valor_bruto' => '5298.99', 'numero' => '8']))));
    }

    public function test_colaborador_sem_empresa_nao_confere_o_tomador(): void
    {
        $f = $this->fechamento();
        Colaborador::whereKey($f->colaborador_id)->update(['empresa_id' => null]);

        $this->assertSame(['tomador'], $this->regras($this->enviar($f, $this->manual())));
    }

    public function test_duplicidade_por_chave_ou_por_cnpj_numero_serie_ignora_recusadas(): void
    {
        $primeira = $this->enviar($this->fechamento(), $this->manual(['chave_acesso' => '123 456']));
        $emissorDaPrimeira = $this->emissor;
        $this->assertSame(StatusNota::Enviada, $primeira->status);

        // Mesmo emissor, número e série de outra nota (o emissor também diverge do cadastro, por isso "contém").
        $mesmoNumero = $this->enviar($this->fechamento(), $this->manual(['cnpj_emissor' => $emissorDaPrimeira]));
        $this->assertContains('duplicidade', $this->regras($mesmoNumero));

        $this->recusaCom(fn () => $this->enviar($this->fechamento(), $this->manual(['chave_acesso' => '123456', 'numero' => '55'])), 'Já existe outra nota com esta chave');

        app(RecusarNota::class)->executar($primeira, User::factory()->create(), 'Erro');
        $depoisDaRecusa = $this->enviar($this->fechamento(), $this->manual(['cnpj_emissor' => $emissorDaPrimeira, 'numero' => '99', 'serie' => '1']));
        $this->assertContains('duplicidade', $this->regras($depoisDaRecusa), 'A nota 99 do mesmo emissor que sobrou (divergente) segue valendo');

        app(RecusarNota::class)->executar($mesmoNumero, User::factory()->create(), 'Duplicada');
        $reconferida = app(\App\Actions\Notas\ConferirNota::class)->executar($depoisDaRecusa->fresh());
        $this->assertNotContains('duplicidade', array_column($reconferida, 'regra'), 'Recusadas não contam como duplicidade');
    }

    public function test_duplicidade_vale_tambem_quando_a_serie_e_nula(): void
    {
        $primeira = $this->enviar($this->fechamento(), $this->manual(['serie' => null]));
        $emissor = $this->emissor;

        $segunda = $this->enviar($this->fechamento(), $this->manual(['serie' => null, 'cnpj_emissor' => $emissor]));

        $this->assertContains('duplicidade', $this->regras($segunda));
        $this->assertSame(StatusNota::Enviada, $primeira->status);
    }

    public function test_colaborador_sem_pessoa_juridica_gera_divergencia_em_vez_de_quebrar(): void
    {
        $f = $this->fechamento();
        PessoaJuridica::whereKey(Colaborador::find($f->colaborador_id)->pessoa_juridica_id)->delete(); // excluída depois do vínculo

        $this->assertContains('emissor', $this->regras($this->enviar($f, $this->manual())));
    }

    public function test_chave_duplicada_nao_deixa_arquivo_orfao_e_reenvio_apaga_os_antigos(): void
    {
        $primeira = $this->enviar($this->fechamento(), $this->manual(['chave_acesso' => '999']));
        $arquivosAntes = count(Storage::disk('local')->allFiles());

        $this->recusaCom(fn () => $this->enviar($this->fechamento(), $this->manual(['chave_acesso' => '999', 'numero' => '2'])), 'mesma chave' === '' ? '' : 'Já existe outra nota com esta chave');
        $this->assertCount($arquivosAntes, Storage::disk('local')->allFiles(), 'A recusa não deixa o PDF novo no disco');

        $f = $primeira->fechamento;
        app(RecusarNota::class)->executar($primeira, User::factory()->create(), 'Erro');
        $caminhoAntigo = $primeira->fresh()->pdf_path;
        $reenviada = $this->enviar($f, $this->manual(['chave_acesso' => '999']));

        Storage::disk('local')->assertMissing($caminhoAntigo);
        Storage::disk('local')->assertExists($reenviada->pdf_path);
        $this->assertSame($primeira->id, $reenviada->id);
    }

    public function test_aprovar_nota_certa_dispara_o_evento_e_divergente_exige_justificativa(): void
    {
        Event::fake([NotaAprovada::class]);
        $financeiro = User::factory()->create();
        $certa = $this->enviar($this->fechamento(), $this->manual());
        $errada = $this->enviar($this->fechamento(), $this->manual(['valor_bruto' => '1', 'numero' => '2']));

        app(AprovarNota::class)->executar($certa, $financeiro);
        $this->assertSame([StatusNota::Aprovada, $financeiro->id], [$certa->fresh()->status, $certa->fresh()->decidido_por]);
        Event::assertDispatched(NotaAprovada::class, fn ($e) => $e->nota->id === $certa->id);

        $this->recusaCom(fn () => app(AprovarNota::class)->executar($errada, $financeiro), 'explique');
        app(AprovarNota::class)->executar($errada, $financeiro, 'Valor combinado por fora');
        $this->assertSame('Valor combinado por fora', $errada->fresh()->justificativa_aprovacao);
        $this->recusaCom(fn () => app(AprovarNota::class)->executar($errada, $financeiro), 'já foi decidida');
        $this->assertTrue(Activity::where('event', 'nota-aprovada')->exists());
    }

    public function test_recusar_exige_motivo_avisa_o_colaborador_e_libera_o_reenvio_na_mesma_linha(): void
    {
        Notification::fake();
        $f = $this->fechamento();
        $colaborador = Colaborador::find($f->colaborador_id);
        $usuarioDele = User::factory()->create(['pessoa_fisica_id' => $colaborador->pessoa_fisica_id]);
        $nota = $this->enviar($f, $this->manual(['valor_bruto' => '1']));

        $this->recusaCom(fn () => app(RecusarNota::class)->executar($nota, User::factory()->create(), '  '), 'motivo');
        app(RecusarNota::class)->executar($nota, User::factory()->create(), 'Valor errado');

        $this->assertSame([StatusNota::Recusada, 'Valor errado'], [$nota->fresh()->status, $nota->fresh()->motivo_recusa]);
        Notification::assertSentTo($usuarioDele, NotaRecusada::class);

        $reenviada = $this->enviar($f, $this->manual());

        $this->assertSame($nota->id, $reenviada->id, 'Reenvio reaproveita a linha');
        $this->assertSame([StatusNota::Enviada, null, null], [$reenviada->status, $reenviada->motivo_recusa, $reenviada->decidido_em]);
        $this->assertSame(1, NotaFiscal::count());
        $this->recusaCom(fn () => $this->enviar($f, $this->manual()), 'Já há uma nota enviada');
    }
}
