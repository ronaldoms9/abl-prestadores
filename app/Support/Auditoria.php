<?php

namespace App\Support;

use App\Models\ImportacaoPessoa;
use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\DocumentoTipo;
use App\Models\Equipe;
use App\Models\IntegracaoLog;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\QualificacaoTipo;
use App\Models\Reajuste;
use App\Models\Sincronizacao;
use App\Models\User;
use App\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

/**
 * Ponto único da auditoria: vocabulário das ações, registro e rótulos.
 *
 * Toda fatia registra pelo registrar() (ou pelo trait Auditavel nos models),
 * para que a tela de auditoria filtre e descreva tudo do mesmo jeito.
 */
class Auditoria
{
    public const LOG = 'sistema';

    /** Vocabulário fixo das ações => rótulo exibido na tela. */
    public const ACOES = [
        'login' => 'Login',
        'logout' => 'Logout',
        'login-falhou' => 'Falha de login',
        'usuario-criado' => 'Usuário criado',
        'usuario-alterado' => 'Usuário alterado',
        'usuario-excluido' => 'Usuário excluído',
        'usuario-senha-alterada' => 'Senha alterada',
        'usuario-perfil-alterado' => 'Perfil do usuário alterado',
        'perfil-criado' => 'Perfil criado',
        'perfil-alterado' => 'Perfil alterado',
        'perfil-excluido' => 'Perfil excluído',
        'perfil-permissoes-alteradas' => 'Permissões do perfil alteradas',
        'pessoa-fisica-criado' => 'Pessoa física criada',
        'pessoa-fisica-alterado' => 'Pessoa física alterada',
        'pessoa-fisica-excluido' => 'Pessoa física excluída',
        'pessoa-juridica-criado' => 'Pessoa jurídica criada',
        'pessoa-juridica-alterado' => 'Pessoa jurídica alterada',
        'pessoa-juridica-excluido' => 'Pessoa jurídica excluída',
        'pessoa-juridica-responsavel-alterado' => 'Responsável da pessoa jurídica alterado',
        'pessoa-dados-bancarios-alterados' => 'Dados bancários alterados',
        'colaborador-criado' => 'Colaborador criado',
        'colaborador-alterado' => 'Colaborador alterado',
        'colaborador-excluido' => 'Colaborador excluído',
        'colaborador-equipe-vinculado' => 'Colaborador incluído em equipe',
        'colaborador-equipe-encerrado' => 'Colaborador retirado de equipe',
        'cargo-criado' => 'Cargo criado',
        'cargo-alterado' => 'Cargo alterado',
        'cargo-excluido' => 'Cargo excluído',
        'remuneracao-definida' => 'Remuneração definida',
        'reajuste-aplicado' => 'Reajuste aplicado',
        'documento-anexado' => 'Documento anexado',
        'documento-excluido' => 'Documento excluído',
        'documento-tipo-criado' => 'Tipo de documento criado',
        'documento-tipo-alterado' => 'Tipo de documento alterado',
        'documento-tipo-excluido' => 'Tipo de documento excluído',
        'qualificacao-tipo-criado' => 'Tipo de qualificação criado',
        'qualificacao-tipo-alterado' => 'Tipo de qualificação alterado',
        'qualificacao-tipo-excluido' => 'Tipo de qualificação excluído',
        'matriz-qualificacoes-alterada' => 'Matriz de qualificações alterada',
        'qualificacao-registrada' => 'Qualificação registrada',
        'qualificacao-excluida' => 'Qualificação excluída',
        'equipe-criado' => 'Equipe criada',
        'equipe-alterado' => 'Equipe alterada',
        'equipe-excluido' => 'Equipe excluída',
        'equipe-gestor-adicionado' => 'Gestor adicionado à equipe',
        'equipe-gestor-encerrado' => 'Gestor retirado da equipe',
        'contrato-modelo-criado' => 'Modelo de contrato criado',
        'contrato-modelo-alterado' => 'Modelo de contrato alterado',
        'contrato-modelo-excluido' => 'Modelo de contrato excluído',
        'contrato-modelo-versionado' => 'Nova versão do modelo de contrato',
        'contrato-criado' => 'Contrato criado',
        'contrato-alterado' => 'Contrato alterado',
        'contrato-excluido' => 'Contrato excluído',
        'contrato-gerado' => 'Contrato gerado',
        'contrato-assinado' => 'Contrato assinado anexado',
        'beneficio-criado' => 'Benefício criado',
        'beneficio-alterado' => 'Benefício alterado',
        'beneficio-excluido' => 'Benefício excluído',
        'beneficio-valor-definido' => 'Valor de benefício definido',
        'beneficio-reajuste-aplicado' => 'Reajuste de benefício aplicado',
        'colaborador-beneficio-vinculado' => 'Benefício vinculado ao colaborador',
        'colaborador-beneficio-encerrado' => 'Benefício do colaborador encerrado',
        'admissao-iniciada' => 'Admissão iniciada',
        'admissao-item-concluido' => 'Item da admissão concluído',
        'admissao-concluida' => 'Admissão concluída',
        'desligamento-registrado' => 'Colaborador desligado',
        'ferias-periodos-gerados' => 'Períodos aquisitivos de férias gerados',
        'ferias-saldo-baixado' => 'Saldo de férias baixado',
        'ferias-saldo-devolvido' => 'Saldo de férias devolvido',
        'ferias-solicitacao-criado' => 'Solicitação de férias criada',
        'ferias-solicitacao-alterado' => 'Solicitação de férias alterada',
        'ferias-solicitacao-excluido' => 'Solicitação de férias excluída',
        'feriado-criado' => 'Feriado criado',
        'feriado-alterado' => 'Feriado alterado',
        'feriado-excluido' => 'Feriado excluído',
        'ocorrencia-criado' => 'Ocorrência criada',
        'ocorrencia-alterado' => 'Ocorrência alterada',
        'ocorrencia-excluido' => 'Ocorrência excluída',
        'ajuste-avulso-criado' => 'Ajuste avulso criado',
        'ajuste-avulso-alterado' => 'Ajuste avulso alterado',
        'ajuste-avulso-excluido' => 'Ajuste avulso excluído',
        'planilha-carregada' => 'Planilha carregada',
        'sincronizacao-solicitada' => 'Sincronização solicitada',
        'integracao-reprocessada' => 'Integração reprocessada',
    ];

    /** Ações que nunca têm autor (quem tentou entrar não está identificado). */
    public const SEM_AUTOR = ['login-falhou'];

    /** Tipo de objeto => [rótulo, atributo que o identifica]. */
    public const OBJETOS = [
        User::class => ['Usuário', 'name'],
        Equipe::class => ['Equipe', 'nome'],
        Cargo::class => ['Cargo', 'nome'],
        QualificacaoTipo::class => ['Tipo de qualificação', 'nome'],
        DocumentoTipo::class => ['Tipo de documento', 'nome'],
        Reajuste::class => ['Reajuste', 'id'],
        ContratoModelo::class => ['Modelo de contrato', 'nome'],
        Contrato::class => ['Contrato', 'numero'],
        Beneficio::class => ['Benefício', 'nome'],
        Colaborador::class => ['Colaborador', 'id'],
        PessoaFisica::class => ['Pessoa física', 'nome'],
        PessoaJuridica::class => ['Pessoa jurídica', 'razao_social'],
        Role::class => ['Perfil', 'display_name'],
        ImportacaoPessoa::class => ['Carga de planilha', 'nome_original'],
        IntegracaoLog::class => ['Chamada ao UAU', 'operacao'],
        Sincronizacao::class => ['Sincronização', 'id'],
    ];

    /** Campo auditado => rótulo. */
    public const CAMPOS = [
        'name' => 'nome',
        'email' => 'e-mail',
        'cpf' => 'CPF',
        'telefone' => 'telefone',
        'ativo' => 'ativo',
        'display_name' => 'nome',
        'description' => 'descrição',
        'nome' => 'nome',
        'nascimento' => 'nascimento',
        'endereco' => 'endereço',
        'razao_social' => 'razão social',
        'nome_fantasia' => 'nome fantasia',
        'regime_tributario' => 'regime tributário',
        'inscricao_municipal' => 'inscrição municipal',
        'inscricao_estadual' => 'inscrição estadual',
        'cnpj' => 'CNPJ',
        'aplica_a' => 'vale para',
        'exige_validade' => 'exige validade',
        'obrigatorio_na_admissao' => 'obrigatório na admissão',
        'categoria' => 'categoria',
        'validade_meses' => 'validade (meses)',
        'faixa_min' => 'faixa mínima',
        'faixa_max' => 'faixa máxima',
        'pessoa_fisica_id' => 'pessoa física',
        'pessoa_juridica_id' => 'pessoa jurídica',
        'cargo_id' => 'cargo',
        'empresa_id' => 'empresa',
        'obra_id' => 'obra',
        'status' => 'situação',
    ];

    /**
     * Registra uma ação do vocabulário. Sem $autor, vale o usuário logado
     * (exceto nas ações de SEM_AUTOR).
     *
     * @param  array<string, mixed>  $dados  Nunca senhas nem hashes.
     */
    public static function registrar(string $acao, ?Model $objeto = null, array $dados = [], ?Authenticatable $autor = null): void
    {
        if (! array_key_exists($acao, self::ACOES)) {
            throw new InvalidArgumentException("Ação de auditoria desconhecida: {$acao}");
        }

        $registro = activity(self::LOG)
            ->event($acao)
            ->withProperties($dados + ['ip' => request()?->ip()]);

        if ($objeto) {
            $registro->performedOn($objeto);
        }

        if (in_array($acao, self::SEM_AUTOR, true)) {
            $registro->causedByAnonymous();
        } elseif ($autor instanceof Model) {
            $registro->causedBy($autor);
        }

        $registro->log($acao);
    }

    /** Rótulo de uma ação ou de um tipo de objeto; sem rótulo, o próprio valor. */
    public static function rotulo(?string $valor): string
    {
        $classe = Relation::getMorphedModel($valor) ?? $valor;

        return self::ACOES[$valor] ?? self::OBJETOS[$classe][0] ?? (string) $valor;
    }

    /** "Usuário · Maria Souza", "Perfil · RH" ou null quando não há objeto. */
    public static function descreverObjeto(Activity $registro): ?string
    {
        if (! $registro->subject_type) {
            return null;
        }

        $classe = Relation::getMorphedModel($registro->subject_type) ?? $registro->subject_type;
        $atributo = self::OBJETOS[$classe][1] ?? null;
        $identificacao = ($atributo ? $registro->subject?->{$atributo} : null)
            ?? $registro->properties['attributes'][$atributo ?? ''] ?? $registro->properties['old'][$atributo ?? ''] ?? null
            ?? '#'.$registro->subject_id;

        return self::rotulo($classe).' · '.$identificacao;
    }

    /** Quem fez: o usuário, o e-mail digitado (falha de login) ou "Sistema". */
    public static function descreverAutor(Activity $registro): string
    {
        return $registro->causer?->name
            ?? $registro->properties['email']
            ?? 'Sistema';
    }

    /** Resumo curto: "e-mail, ativo", "3 permissões incluídas", "RH → Gestor". */
    public static function resumirAlteracoes(Activity $registro): string
    {
        $propriedades = $registro->properties;

        if ($registro->event === 'perfil-permissoes-alteradas') {
            return collect([
                self::contar(count($propriedades['incluidas'] ?? []), 'incluída', 'incluídas'),
                self::contar(count($propriedades['removidas'] ?? []), 'removida', 'removidas'),
            ])->filter()->implode(', ');
        }

        if ($registro->event === 'usuario-perfil-alterado') {
            return implode(', ', $propriedades['antes'] ?? []) ?: '—';
        }

        if ($registro->event === 'usuario-senha-alterada') {
            return 'senha';
        }

        return collect(array_keys($propriedades['attributes'] ?? []))
            ->map(fn ($campo) => self::CAMPOS[$campo] ?? $campo)
            ->implode(', ');
    }

    /**
     * Linhas campo / antes / depois para o detalhe.
     *
     * @return array<int, array{campo: string, antes: mixed, depois: mixed}>
     */
    public static function alteracoes(Activity $registro): array
    {
        $novos = $registro->properties['attributes'] ?? [];
        $antigos = $registro->properties['old'] ?? [];

        return collect(array_unique([...array_keys($antigos), ...array_keys($novos)]))
            ->map(fn ($campo) => [
                'campo' => self::CAMPOS[$campo] ?? $campo,
                'antes' => self::formatar($antigos[$campo] ?? null),
                'depois' => self::formatar($novos[$campo] ?? null),
            ])
            ->values()
            ->all();
    }

    private static function formatar(mixed $valor): string
    {
        return match (true) {
            is_bool($valor) => $valor ? 'sim' : 'não',
            $valor === null || $valor === '' => '—',
            is_array($valor) => implode(', ', $valor),
            default => (string) $valor,
        };
    }

    private static function contar(int $quantidade, string $singular, string $plural): ?string
    {
        if ($quantidade === 0) {
            return null;
        }

        return $quantidade === 1
            ? "1 permissão {$singular}"
            : "{$quantidade} permissões {$plural}";
    }
}
