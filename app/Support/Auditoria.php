<?php

namespace App\Support;

use App\Models\ImportacaoPessoa;
use App\Models\IntegracaoLog;
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
        'planilha-carregada' => 'Planilha carregada',
        'sincronizacao-solicitada' => 'Sincronização solicitada',
        'integracao-reprocessada' => 'Integração reprocessada',
    ];

    /** Ações que nunca têm autor (quem tentou entrar não está identificado). */
    public const SEM_AUTOR = ['login-falhou'];

    /** Tipo de objeto => [rótulo, atributo que o identifica]. */
    public const OBJETOS = [
        User::class => ['Usuário', 'name'],
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
