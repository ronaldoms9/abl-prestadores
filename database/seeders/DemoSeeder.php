<?php

namespace Database\Seeders;

use App\Actions\Beneficios\DefinirValorBeneficio;
use App\Actions\Beneficios\SalvarBeneficiosPadraoCargo;
use App\Actions\Beneficios\VincularBeneficio;
use App\Actions\Cadastros\SalvarColaborador;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Actions\Contratos\SalvarContratoModelo;
use App\Actions\Cadastros\SalvarPessoaFisica;
use App\Actions\Cadastros\SalvarPessoaJuridica;
use App\Actions\Documentos\AnexarDocumento;
use App\Actions\Equipes\AdicionarGestor;
use App\Actions\Equipes\VincularColaborador;
use App\Actions\Qualificacoes\RegistrarQualificacao;
use App\Actions\Qualificacoes\SalvarMatrizQualificacoes;
use App\Actions\Remuneracoes\DefinirRemuneracaoInicial;
use App\Actions\Remuneracoes\ReajustarRemuneracao;
use App\Enums\CategoriaQualificacao;
use App\Enums\FormaReajuste;
use App\Enums\MotivoAditivo;
use App\Enums\TipoCalculoBeneficio;
use App\Enums\StatusColaborador;
use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\DocumentoTipo;
use App\Models\Empresa;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\QualificacaoTipo;
use App\Models\User;
use App\Role;
use App\Support\Endereco;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Dados fictícios para demonstrar o sistema (apresentação à ABL PRIME).
 * Usa as mesmas ações das telas, então histórico, auditoria e regras de
 * vigência saem como no uso real. Fora do DatabaseSeeder:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Só roda fora de produção e só em banco sem pessoas cadastradas (não
 * sobrescreve nem duplica nada). Usuários de demonstração: senha demo@123.
 */
class DemoSeeder extends Seeder
{
    public const SENHA = 'demo@123';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder não roda em produção.');
        }

        if (PessoaFisica::withTrashed()->exists()) {
            $this->command?->warn('Já existem pessoas cadastradas: nada foi criado.');

            return;
        }

        $this->call([PermissionSeeder::class, PerfilSeeder::class, UserCreateSeeder::class, DocumentoTipoSeeder::class, AdmissaoChecklistItemSeeder::class]);

        $admin = User::where('email', 'ronaldo@rmsti.com.br')->first() ?? User::query()->orderBy('id')->firstOrFail();
        Auth::login($admin);

        $usuarios = $this->usuarios();
        $empresas = $this->empresasEObras();
        $cargos = $this->cargos();
        $qualificacoes = $this->qualificacoesEMatriz($cargos);
        $equipes = $this->equipes($usuarios);
        $this->prestadores($admin, $empresas, $cargos, $equipes, $qualificacoes);
        $this->contratosEBeneficios($cargos);

        Auth::logout();

        $this->command?->info('Dados de demonstração criados.');
        $this->command?->table(['Perfil', 'E-mail', 'Senha'], [
            ['Administrador', $admin->email, '(a sua)'],
            ['RH', 'rh@demo.abl', self::SENHA],
            ['Gestor (Obras)', 'gestor.obras@demo.abl', self::SENHA],
            ['Gestor (Projetos)', 'gestor.projetos@demo.abl', self::SENHA],
            ['Financeiro', 'financeiro@demo.abl', self::SENHA],
        ]);
    }

    /** @return array<string, User> */
    private function usuarios(): array
    {
        $lista = [
            'rh' => ['Marina Teixeira (RH)', 'rh@demo.abl', 'rh'],
            'obras' => ['Carlos Eduardo Pires (Gestor)', 'gestor.obras@demo.abl', 'gestor'],
            'projetos' => ['Luciana Prado (Gestora)', 'gestor.projetos@demo.abl', 'gestor'],
            'financeiro' => ['Roberto Nogueira (Financeiro)', 'financeiro@demo.abl', 'financeiro'],
        ];

        $usuarios = [];
        foreach ($lista as $chave => [$nome, $email, $perfil]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $nome,
                'email_verified_at' => now(),
                'password' => Hash::make(self::SENHA),
                'ativo' => true,
            ]);
            $user->roles()->syncWithoutDetaching([Role::where('name', $perfil)->firstOrFail()->id]);
            $usuarios[$chave] = $user;
        }

        return $usuarios;
    }

    /** @return array{empresas: array<string, Empresa>, obras: array<string, Obra>} */
    private function empresasEObras(): array
    {
        $end = fn (string $rua, string $n, string $bairro, string $cep) => new Endereco($rua, $n, null, $bairro, 'Goiânia', 'GO', $cep);

        $incorporadora = Empresa::create([
            'uau_codigo' => 1, 'cnpj' => '11222333000181', 'razao_social' => 'ABL PRIME Incorporadora Ltda',
            'telefone' => '6232345600', 'ativo' => true, 'endereco' => $end('Avenida T-9', '1500', 'Setor Bueno', '74215000'),
        ]);
        $engenharia = Empresa::create([
            'uau_codigo' => 2, 'cnpj' => '45723174000110', 'razao_social' => 'ABL PRIME Engenharia e Construções S.A.',
            'telefone' => '6232345700', 'ativo' => true, 'endereco' => $end('Rua 90', '320', 'Setor Sul', '74093120'),
        ]);

        $obras = [];
        foreach ([
            ['res-aurora', $incorporadora, 'OBR-101', 'Residencial Aurora Prime', 'Alameda Ricardo Paranhos', '2025-02-03', '2027-06-30'],
            ['torre-jardins', $incorporadora, 'OBR-102', 'Torre Jardins Prime', 'Avenida Jamel Cecílio', '2025-08-11', '2028-03-31'],
            ['business', $engenharia, 'OBR-201', 'Prime Business Center', 'Avenida 85', '2024-11-04', '2027-01-29'],
            ['condominio', $engenharia, 'OBR-202', 'Condomínio Horizonte Verde', 'Rodovia GO-020', '2026-03-02', '2028-09-15'],
        ] as [$chave, $empresa, $codigo, $nome, $rua, $inicio, $fim]) {
            $obras[$chave] = Obra::create([
                'empresa_id' => $empresa->id, 'uau_codigo' => $codigo, 'descricao' => $nome, 'status_uau' => 1,
                'tipo_uau' => 'OBR', 'data_inicio' => $inicio, 'data_fim' => $fim, 'ativo' => true,
                'endereco' => new Endereco($rua, 's/n', null, 'Goiânia', 'Goiânia', 'GO', '74000000'),
            ]);
        }

        return ['empresas' => ['incorporadora' => $incorporadora, 'engenharia' => $engenharia], 'obras' => $obras];
    }

    /** @return array<string, Cargo> */
    private function cargos(): array
    {
        $cargos = [];
        foreach ([
            'engenheiro' => ['Engenheiro Civil', 'Responsável técnico pela execução da obra.', 9000, 14000],
            'coordenador' => ['Coordenador de Projetos', 'Coordena projetos e compatibilização.', 10000, 15000],
            'arquiteto' => ['Arquiteto', 'Desenvolve e acompanha projetos arquitetônicos.', 7000, 11000],
            'mestre' => ['Mestre de Obras', 'Lidera as frentes de trabalho no canteiro.', 5500, 8000],
            'seguranca' => ['Técnico em Segurança do Trabalho', 'Inspeções e treinamentos de segurança.', 4000, 6500],
            'eletricista' => ['Eletricista', 'Instalações elétricas da obra.', 3200, 5000],
            'encarregado' => ['Encarregado', 'Acompanha equipes de execução.', 4500, 6500],
            'administrativo' => ['Analista Administrativo', 'Rotinas administrativas e de suprimentos.', 3500, 5500],
        ] as $chave => [$nome, $descricao, $min, $max]) {
            $cargos[$chave] = Cargo::create(['nome' => $nome, 'descricao' => $descricao, 'faixa_min' => $min, 'faixa_max' => $max, 'ativo' => true]);
        }

        return $cargos;
    }

    /**
     * @param  array<string, Cargo>  $cargos
     * @return array<string, QualificacaoTipo>
     */
    private function qualificacoesEMatriz(array $cargos): array
    {
        $tipos = [];
        foreach ([
            'nr35' => ['NR-35 Trabalho em altura', CategoriaQualificacao::TreinamentoSeguranca, 24],
            'nr10' => ['NR-10 Segurança em eletricidade', CategoriaQualificacao::TreinamentoSeguranca, 24],
            'nr18' => ['NR-18 Segurança na construção', CategoriaQualificacao::TreinamentoSeguranca, 12],
            'socorros' => ['Primeiros socorros', CategoriaQualificacao::Curso, 12],
            'crea' => ['Registro no CREA/CAU', CategoriaQualificacao::Habilitacao, null],
            'cnh' => ['CNH categoria B', CategoriaQualificacao::Habilitacao, 60],
            'pmp' => ['Gerenciamento de projetos (PMP)', CategoriaQualificacao::Certificacao, 36],
        ] as $chave => [$nome, $categoria, $meses]) {
            $tipos[$chave] = QualificacaoTipo::create(['nome' => $nome, 'categoria' => $categoria, 'validade_meses' => $meses, 'ativo' => true]);
        }

        $o = SalvarMatrizQualificacoes::OBRIGATORIA;
        $p = SalvarMatrizQualificacoes::OPCIONAL;
        $celulas = [];
        foreach ([
            'engenheiro' => ['crea' => $o, 'nr18' => $o, 'nr35' => $o, 'pmp' => $p],
            'coordenador' => ['crea' => $o, 'nr18' => $o, 'pmp' => $o],
            'arquiteto' => ['crea' => $o, 'pmp' => $p],
            'mestre' => ['nr18' => $o, 'nr35' => $o, 'socorros' => $p],
            'seguranca' => ['nr18' => $o, 'nr35' => $o, 'nr10' => $o, 'socorros' => $o],
            'eletricista' => ['nr10' => $o, 'nr35' => $o, 'nr18' => $o],
            'encarregado' => ['nr18' => $o, 'nr35' => $o, 'cnh' => $p],
            'administrativo' => ['cnh' => $p],
        ] as $cargo => $linha) {
            foreach ($linha as $tipo => $estado) {
                $celulas[$cargos[$cargo]->id][$tipos[$tipo]->id] = $estado;
            }
        }
        app(SalvarMatrizQualificacoes::class)->executar($celulas);

        return $tipos;
    }

    /**
     * @param  array<string, User>  $usuarios
     * @return array<string, \App\Models\Equipe>
     */
    private function equipes(array $usuarios): array
    {
        $adicionar = app(AdicionarGestor::class);
        $inicio = now()->subMonths(10);

        $equipes = [
            'obras' => \App\Models\Equipe::create(['nome' => 'Obras — Residencial Aurora', 'descricao' => 'Execução do Residencial Aurora Prime.', 'ativo' => true]),
            'torre' => \App\Models\Equipe::create(['nome' => 'Obras — Torre Jardins', 'descricao' => 'Execução da Torre Jardins Prime.', 'ativo' => true]),
            'projetos' => \App\Models\Equipe::create(['nome' => 'Projetos e Compatibilização', 'descricao' => 'Arquitetura, projetos e planejamento.', 'ativo' => true]),
        ];

        $adicionar->executar($equipes['obras'], $usuarios['obras'], $inicio);
        $adicionar->executar($equipes['torre'], $usuarios['obras'], $inicio);
        $adicionar->executar($equipes['projetos'], $usuarios['projetos'], $inicio);

        return $equipes;
    }

    /**
     * @param  array{empresas: array<string, Empresa>, obras: array<string, Obra>}  $empresas
     * @param  array<string, Cargo>  $cargos
     * @param  array<string, \App\Models\Equipe>  $equipes
     * @param  array<string, QualificacaoTipo>  $q
     */
    private function prestadores(User $admin, array $empresas, array $cargos, array $equipes, array $q): void
    {
        $faker = fake('pt_BR');
        $faker->seed(2026);

        $docTipo = fn (string $parte) => DocumentoTipo::where('nome', 'like', "%{$parte}%")->firstOrFail();
        $aso = $docTipo('ASO');
        $identidade = $docTipo('identidade');
        $cnd = $docTipo('Certidão negativa');
        $alvara = $docTipo('Alvará');
        $cartaoCnpj = $docTipo('Cartão CNPJ');

        $salvarPf = app(SalvarPessoaFisica::class);
        $salvarPj = app(SalvarPessoaJuridica::class);
        $salvarColab = app(SalvarColaborador::class);
        $vincular = app(VincularColaborador::class);
        $remunerar = app(DefinirRemuneracaoInicial::class);
        $reajustar = app(ReajustarRemuneracao::class);
        $anexar = app(AnexarDocumento::class);
        $registrar = app(RegistrarQualificacao::class);

        $bancos = ['001 - Banco do Brasil', '237 - Bradesco', '341 - Itaú Unibanco', '104 - Caixa Econômica', '260 - Nubank'];

        // chave => [nome, e-mail, PJ (razão, fantasia, regime), cargo, empresa, obra, equipe, status, meses de casa, salário, reajustes[meses atrás, forma, valor]]
        $pessoas = [
            ['Rafael Moreira Lima', 'Moreira Engenharia Consultiva LTDA', 'Moreira Engenharia', 'Lucro Presumido', 'engenheiro', 'incorporadora', 'res-aurora', 'obras', 'ativo', 14, 11000, [[6, 'percentual', 5]]],
            ['Fernanda Albuquerque Costa', 'FAC Projetos e Coordenação LTDA', 'FAC Projetos', 'Simples Nacional', 'coordenador', 'incorporadora', 'torre-jardins', 'projetos', 'ativo', 11, 12500, [[4, 'valor', 800]]],
            ['Thiago Henrique Barros', 'THB Arquitetura LTDA', 'THB Arquitetura', 'Simples Nacional', 'arquiteto', 'engenharia', 'business', 'projetos', 'ativo', 9, 8200, []],
            ['José Aparecido Ferreira', 'JAF Construções ME', 'JAF Construções', 'MEI', 'mestre', 'incorporadora', 'res-aurora', 'obras', 'ativo', 18, 6800, [[12, 'percentual', 4], [3, 'percentual', 6]]],
            ['Patrícia Gomes Duarte', 'PGD Segurança do Trabalho LTDA', 'PGD Segurança', 'Simples Nacional', 'seguranca', 'incorporadora', 'res-aurora', 'obras', 'ativo', 8, 5200, []],
            ['Marcos Vinícius Andrade', 'MVA Instalações Elétricas ME', 'MVA Elétrica', 'MEI', 'eletricista', 'incorporadora', 'torre-jardins', 'torre', 'ativo', 7, 4100, [[2, 'valor', 250]]],
            ['Ana Clara Rezende', 'ACR Consultoria Administrativa LTDA', 'ACR Consultoria', 'Simples Nacional', 'administrativo', 'incorporadora', null, 'projetos', 'ativo', 6, 4300, []],
            ['Wellington Souza Pinto', 'WSP Obras e Serviços ME', 'WSP Obras', 'MEI', 'encarregado', 'incorporadora', 'torre-jardins', 'torre', 'ativo', 5, 5400, []],
            ['Camila Ribeiro Neves', 'CRN Engenharia LTDA', 'CRN Engenharia', 'Lucro Presumido', 'engenheiro', 'engenharia', 'business', 'torre', 'ativo', 3, 10200, []],
            ['Diego Martins Oliveira', 'DMO Elétrica e Automação ME', 'DMO Elétrica', 'Simples Nacional', 'eletricista', 'incorporadora', 'res-aurora', 'obras', 'ativo', 2, 3900, []],
            ['Larissa Fonseca Cardoso', 'LFC Projetos Arquitetônicos LTDA', 'LFC Arquitetura', 'Simples Nacional', 'arquiteto', 'engenharia', 'condominio', 'projetos', 'pre', 0, null, []],
            ['Eduardo Santana Rocha', 'ESR Gestão de Obras LTDA', 'ESR Gestão', 'Lucro Presumido', 'mestre', 'engenharia', 'condominio', null, 'pre', 0, null, []],
            ['Sérgio Luiz Camargo', 'SLC Serviços Técnicos ME', 'SLC Técnicos', 'MEI', 'encarregado', 'incorporadora', 'res-aurora', 'obras', 'desligado', 16, 5000, [[8, 'percentual', 5]]],
        ];

        foreach ($pessoas as $i => [$nome, $razao, $fantasia, $regime, $cargo, $empresa, $obra, $equipe, $situacao, $meses, $salario, $reajustes]) {
            $banco = $bancos[$i % count($bancos)];
            $partes = explode(' ', $nome);
            $emailBase = strtolower(\Illuminate\Support\Str::ascii($partes[0].'.'.end($partes)));

            $pf = $salvarPf->executar(null, [
                'cpf' => $faker->cpf(false),
                'nome' => $nome,
                'nascimento' => now()->subYears(28 + ($i * 2) % 17)->subDays($i * 37)->toDateString(),
                'email' => "{$emailBase}@email.com",
                'telefone' => '62'.$faker->numerify('9########'),
                'endereco' => ['logradouro' => $faker->streetName(), 'numero' => (string) $faker->buildingNumber(), 'bairro' => 'Setor '.$faker->randomElement(['Bueno', 'Marista', 'Oeste', 'Jardim América']), 'cidade' => 'Goiânia', 'uf' => 'GO', 'cep' => '74'.$faker->numerify('######')],
                'bancos' => [['banco' => $banco, 'agencia' => $faker->numerify('####'), 'conta' => $faker->numerify('#####-#'), 'tipo' => 'corrente', 'tipo_chave_pix' => 'cpf', 'chave_pix' => null]],
            ]);

            $pj = $salvarPj->executar(null, [
                'cnpj' => $faker->cnpj(false),
                'razao_social' => $razao,
                'nome_fantasia' => $fantasia,
                'regime_tributario' => $regime,
                'inscricao_municipal' => $faker->numerify('########'),
                'email' => 'contato@'.strtolower(str_replace(' ', '', \Illuminate\Support\Str::ascii($fantasia))).'.com.br',
                'telefone' => '62'.$faker->numerify('3#######'),
                'endereco' => ['logradouro' => $faker->streetName(), 'numero' => (string) $faker->buildingNumber(), 'bairro' => 'Setor Marista', 'cidade' => 'Goiânia', 'uf' => 'GO', 'cep' => '74150'.$faker->numerify('###')],
                'responsavel_id' => $pf->id,
                'bancos' => [['banco' => $banco, 'agencia' => $faker->numerify('####'), 'conta' => $faker->numerify('#####-#'), 'tipo' => 'corrente', 'tipo_chave_pix' => 'cnpj', 'chave_pix' => null]],
            ]);

            $colab = $salvarColab->executar(null, [
                'pessoa_fisica_id' => $pf->id,
                'pessoa_juridica_id' => $pj->id,
                'cargo_id' => $cargos[$cargo]->id,
                'empresa_id' => $empresas['empresas'][$empresa]->id,
                'obra_id' => $obra ? $empresas['obras'][$obra]->id : null,
            ]);

            if ($situacao === 'pre') {
                if ($equipe) {
                    $vincular->executar($colab, $equipes[$equipe], now()->subDays(5));
                }

                continue;
            }

            $admissao = now()->subMonths($meses)->startOfDay();
            $colab->forceFill(['status' => StatusColaborador::Ativo, 'data_admissao' => $admissao])->save();

            if ($equipe) {
                $vincular->executar($colab, $equipes[$equipe], $admissao);
            }

            $remunerar->executar($colab, $salario, $admissao);
            foreach ($reajustes as [$atras, $forma, $valor]) {
                $reajustar->executar($colab, $forma === 'percentual' ? FormaReajuste::Percentual : FormaReajuste::Valor, $valor, now()->subMonths($atras)->startOfDay(), $admin);
            }

            if ($situacao === 'desligado') {
                $colab->forceFill([
                    'status' => StatusColaborador::Desligado,
                    'data_desligamento' => now()->subMonths(1)->toDateString(),
                    'motivo_desligamento' => 'Fim do contrato de prestação de serviços.',
                ])->save();
            }

            // Documentos: ASO em cenários diferentes (ok, a vencer, vencido) para os alertas.
            if ($situacao === 'ativo') {
                $validadeAso = match ($i % 4) {
                    0 => now()->addMonths(8),
                    1 => now()->addDays(12),
                    2 => now()->subDays(20),
                    default => now()->addMonths(3),
                };
                $anexar->executar($colab, $aso, $this->pdf("aso-{$emailBase}.pdf"), $validadeAso, 'ASO periódico.');
                $anexar->executar($pf, $identidade, $this->pdf("rg-{$emailBase}.pdf"));
                $anexar->executar($pj, $cartaoCnpj, $this->pdf("cartao-cnpj-{$emailBase}.pdf"));
                $anexar->executar($pj, $cnd, $this->pdf("cnd-{$emailBase}.pdf"), match ($i % 3) {
                    0 => now()->addDays(25),
                    1 => now()->addMonths(5),
                    default => now()->subDays(7),
                });
                if ($i % 2 === 0) {
                    $anexar->executar($pj, $alvara, $this->pdf("alvara-{$emailBase}.pdf"), now()->addMonths(10));
                }
            }

            // Qualificações: umas em dia, outras a vencer, vencidas ou ausentes.
            $this->qualificacoes($registrar, $colab, $cargo, $q, $i);
        }
    }

    /** @param  array<string, QualificacaoTipo>  $q */
    private function qualificacoes(RegistrarQualificacao $registrar, Colaborador $colab, string $cargo, array $q, int $i): void
    {
        $pdf = fn (string $n) => $this->pdf($n);
        $instituicao = 'SENAI Goiás';

        $registros = match ($cargo) {
            'engenheiro' => [['crea', 40], ['nr18', $i % 2 ? 11 : 3], ['nr35', 20], ['pmp', 10]],
            'coordenador' => [['crea', 60], ['nr18', 2], ['pmp', 30]],
            'arquiteto' => [['crea', 36]],
            'mestre' => [['nr18', 5], ['nr35', 26]],
            'seguranca' => [['nr18', 4], ['nr35', 6], ['nr10', 8], ['socorros', 3]],
            'eletricista' => [['nr10', 25], ['nr35', 9]],
            'encarregado' => [['nr18', 13], ['cnh', 24]],
            default => [['cnh', 12]],
        };

        foreach ($registros as [$tipo, $mesesAtras]) {
            $registrar->executar($colab, $q[$tipo], now()->subMonths($mesesAtras)->subDays($i)->startOfDay(), $instituicao, $pdf("cert-{$tipo}-{$colab->id}.pdf"));
        }
    }

    private function pdf(string $nome): UploadedFile
    {
        $conteudo = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $caminho = Storage::disk('local')->path('demo-tmp/'.$nome);
        @mkdir(dirname($caminho), 0775, true);
        file_put_contents($caminho, $conteudo);

        return new UploadedFile($caminho, $nome, 'application/pdf', null, true);
    }

    /**
     * Benefícios com valor, benefícios padrão por cargo, modelos de exemplo e um
     * contrato gerado para cada colaborador ativo (E3). Os textos dos modelos são
     * de exemplo: o jurídico da ABL PRIME entrega os reais na homologação.
     *
     * @param  array<string, Cargo>  $cargos
     */
    private function contratosEBeneficios(array $cargos): void
    {
        $definir = app(DefinirValorBeneficio::class);
        $inicio = now()->subMonths(18)->startOfDay();

        $beneficios = [];
        foreach ([
            'vt' => ['Vale transporte', TipoCalculoBeneficio::FixoMensal, 300],
            'va' => ['Vale alimentação', TipoCalculoBeneficio::PorDiaTrabalhado, 35],
            'saude' => ['Plano de saúde', TipoCalculoBeneficio::FixoMensal, 180],
        ] as $chave => [$nome, $tipo, $valor]) {
            $beneficios[$chave] = Beneficio::create(['nome' => $nome, 'tipo_calculo' => $tipo, 'ativo' => true]);
            $definir->executar($beneficios[$chave], $valor, $inicio);
        }

        $padrao = app(SalvarBeneficiosPadraoCargo::class);
        foreach ($cargos as $cargo) {
            $padrao->executar($cargo, [$beneficios['vt']->id, $beneficios['va']->id]);
        }

        $salvarModelo = app(SalvarContratoModelo::class);
        $aviso = '<p><em>MODELO DE EXEMPLO — substituir pelo texto do jurídico da ABL PRIME.</em></p>';
        $modelo = fn (string $nome, string $tipo, ?string $motivo, string $corpo) => $salvarModelo->executar(null, ['nome' => $nome, 'tipo' => $tipo, 'motivo' => $motivo, 'corpo_html' => $aviso.$corpo]);

        $contrato = $modelo('Contrato de prestação de serviços (exemplo)', 'contrato', null, '<h3>CONTRATO DE PRESTAÇÃO DE SERVIÇOS Nº {{contrato.numero}}</h3>'
            .'<p>Pelo presente instrumento, {{empresa.razao_social}}, CNPJ {{empresa.cnpj}}, contrata {{pj.razao_social}}, CNPJ {{pj.cnpj}}, representada por {{pj.responsavel_nome}}, CPF {{pj.responsavel_cpf}}, para a prestação de serviços de {{cargo.nome}}.</p>'
            .'<p><strong>Remuneração.</strong> {{remuneracao.valor}} ({{remuneracao.valor_extenso}}) mensais, a partir de {{remuneracao.vigencia_inicio}}.</p>'
            .'<p><strong>Benefícios.</strong></p>{{beneficios.lista}}'
            .'<p><strong>Vigência.</strong> A partir de {{contrato.vigencia_inicio}}.</p><p>Goiânia, {{contrato.data}}.</p>');
        $modelo('Aditivo de reajuste (exemplo)', 'aditivo', MotivoAditivo::Reajuste->value, '<h3>ADITIVO {{contrato.numero}}</h3>'
            .'<p>Ao contrato {{contrato.numero_pai}} firmado com {{pj.razao_social}}: a remuneração passa de {{aditivo.valor_anterior}} para {{aditivo.valor_novo}} ({{aditivo.valor_novo_extenso}}) a partir de {{contrato.vigencia_inicio}}.</p><p>Goiânia, {{contrato.data}}.</p>');
        $modelo('Aditivo de prorrogação (exemplo)', 'aditivo', MotivoAditivo::Prorrogacao->value, '<h3>ADITIVO {{contrato.numero}}</h3>'
            .'<p>Fica prorrogado o contrato {{contrato.numero_pai}} firmado com {{pj.razao_social}} até {{aditivo.nova_vigencia_fim}}.</p><p>Goiânia, {{contrato.data}}.</p>');
        $modelo('Aditivo genérico (exemplo)', 'aditivo', null, '<h3>ADITIVO {{contrato.numero}}</h3>'
            .'<p>Ao contrato {{contrato.numero_pai}} firmado com {{pj.razao_social}}, a partir de {{contrato.vigencia_inicio}}: {{aditivo.motivo}}. {{aditivo.cargo_anterior}} {{aditivo.cargo_novo}} {{aditivo.beneficios_alteracao}}</p>');
        $modelo('Distrato (exemplo)', 'distrato', null, '<h3>DISTRATO {{contrato.numero}}</h3>'
            .'<p>As partes distratam o contrato {{contrato.numero_pai}} firmado com {{pj.razao_social}}, com efeitos a partir de {{desligamento.data}}. Motivo: {{desligamento.motivo}}.</p><p>Goiânia, {{contrato.data}}.</p>');

        $vincular = app(VincularBeneficio::class);
        $criar = app(CriarContrato::class);
        $gerar = app(GerarContrato::class);

        foreach (Colaborador::query()->where('status', StatusColaborador::Ativo)->orderBy('id')->get() as $i => $colaborador) {
            $admissao = $colaborador->data_admissao->copy()->startOfDay();
            $vincular->executar($colaborador, $beneficios['vt'], $admissao);
            $vincular->executar($colaborador, $beneficios['va'], $admissao);
            if ($i % 2 === 0) {
                $vincular->executar($colaborador, $beneficios['saude'], $admissao, $i === 0 ? 150 : null);
            }

            // Um contrato com prazo curto, para mostrar o alerta de vencimento.
            $fim = $i === 1 ? now()->addDays(15)->startOfDay() : ($i === 2 ? now()->subDays(3)->startOfDay() : null);
            $gerar->executar($criar->executar($colaborador, $contrato, $admissao, $fim), $admissao);
        }
    }
}
