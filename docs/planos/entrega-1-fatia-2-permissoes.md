# E1 · Fatia 2 — Perfis, permissões e auditoria · 10 h · semana 1

Detalha a fatia 2 do [plano da Entrega 1](entrega-1-base-uau-leitura.md).
Depende da fatia 1 (a limpeza remove os testes quebrados; o menu por permissão já existe).

Ao fim desta fatia:
- as permissões da E1 e os cinco perfis iniciais estão semeados. O seeder pode
  rodar de novo sem desfazer o que o admin mudou na tela;
- a senha tem política única (8+, letras e números) em todos os pontos de troca;
- usuário inativo não entra e é desconectado se já estiver dentro;
- login, usuários e perfis ficam registrados na auditoria, que tem tela própria.

**Regra (README, D2):** o código só verifica permissões, nunca o nome de um perfil.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 2.1 | Seeders de permissões e perfis | 2 |
| 2.2 | Correções de autorização (rota, Horizon, `User::can`) | 1 |
| 2.3 | Política de senha | 1 |
| 2.4 | Usuário ativo/inativo | 1,5 |
| 2.5 | Auditoria: registro dos eventos | 2 |
| 2.6 | Auditoria: tela | 2,5 |
| | **Total** | **10** |

Branch `e1/fatia-2-permissoes`. Os testes de cada tarefa entram no mesmo commit.

---

## 2.1 Seeders de permissões e perfis — 2 h

**Estado atual**
- `PermissionSeeder` cria as 8 permissões de usuário e grupo e o perfil `superadmin`.
  A cada execução, apaga e recria os vínculos em `permission_role`.
- `UserCreateSeeder` faz `roles()->attach(1)` (id fixo) e quebra na segunda
  execução (e-mail único).

**`PermissionSeeder`** (reescrito): só cuida das permissões.
- Lista no formato atual (`name`, `display_name`, `description`, `module`),
  com as 9 permissões novas da E1:

  | `name` | `display_name` | `module` |
  | --- | --- | --- |
  | `empresa-list` | Ver empresas | Cadastros UAU |
  | `obra-list` | Ver obras | Cadastros UAU |
  | `pessoa-list` | Ver pessoas | Pessoas |
  | `pessoa-importar` | Carregar planilha de pessoas | Pessoas |
  | `integracao-list` | Ver monitoramento das integrações | Integrações |
  | `integracao-sincronizar` | Sincronizar agora | Integrações |
  | `integracao-reenviar` | Reprocessar falha de integração | Integrações |
  | `auditoria-list` | Ver auditoria | Configurações |
  | `horizon-acessar` | Acessar painel de filas (Horizon) | Configurações |

  Os módulos "Usuários" e "Grupos De Acesso" passam a "Usuários" e "Perfis".
- `Permission::updateOrCreate(['name' => ...], [...])`: atualiza rótulo e módulo
  sem duplicar.
- Devolve, ou guarda numa propriedade estática, a lista das permissões **criadas
  nesta execução** (`wasRecentlyCreated`), que o `PerfilSeeder` usa.

**`PerfilSeeder`** (novo): perfis e vínculos.
- Mapa `perfil → permissões padrão`, como na tabela do plano da E1:
  `administrador`, `rh`, `gestor`, `financeiro`, `colaborador`.
- O perfil `superadmin` existente é renomeado para `administrador`
  (`display_name` "Administrador"), mantendo o id e os usuários ligados.
- `Role::firstOrCreate(['name' => ...])`.
- Regra de vínculo que respeita a edição do admin:
  - **Perfil criado agora** → recebe todas as permissões padrão.
  - **Perfil que já existia** → recebe só as permissões padrão que foram
    **criadas nesta execução**. Uma permissão que o admin tirou de um perfil
    não volta quando o seeder roda de novo (deploy das E2–E6).
  - **Administrador** → `syncWithoutDetaching(Permission::pluck('id'))` em toda
    execução. Toda permissão nova chega ao admin.
- Isso substitui o `delete` + `insert` atual em `permission_role`.

**`UserCreateSeeder`**: `User::firstOrCreate(['email' => ...])` e
`syncWithoutDetaching` com o id do perfil `administrador`, buscado pelo
`name`. É o único lugar em que o código usa o nome de um perfil, e só no seed.
A senha inicial `mudar@123` atende à nova política (2.3).

**`DatabaseSeeder`**: `PermissionSeeder` → `PerfilSeeder` → `UserCreateSeeder`.
Usuários de teste por perfil ficam para a fatia 9 (`HomologacaoSeeder`).

**Testes** (`tests/Feature/SeedersTest.php`)
- Rodar duas vezes não duplica permissões, perfis nem vínculos.
- Tirar `pessoa-importar` do RH e rodar de novo: continua sem.
- Criar uma permissão nova na lista e rodar: o admin recebe; um perfil que a tem como padrão também.

## 2.2 Correções de autorização — 1 h

**Rota de grupos** (`routes/web.php`): `grupo.form` usa
`permission:user-grupo|grupo-edit`, e `user-grupo` não existe. Quem só tem
`grupo-create` não abre o formulário. Trocar por `permission:grupo-create|grupo-edit`.

**Gate do Horizon** (`HorizonServiceProvider::gate()`):
```php
Gate::define('viewHorizon', fn ($user = null) => $user?->can('horizon-acessar') ?? false);
```

**`User::can()`**: o override troca o segundo parâmetro do Laravel
(`$arguments`) por `$full`. Uma chamada de Policy (`$user->can('update', $pessoa)`,
`@can`, `$this->authorize()`) passaria o model como `$full` e procuraria uma
permissão chamada `update`. As Policies entram na E2 (D2), então corrigir agora:
```php
public function can($abilities, $arguments = [])
{
    if (! is_bool($arguments) && Gate::has(...)) {   // ability definida no Gate/Policy
        return parent::can($abilities, $arguments);
    }
    // caminho atual por nome de permissão; $arguments === true equivale a $full
}
```
O formato `permission:a|b:all` do middleware e o `@permission($x, true)`
continuam como estão.

**Testes**
- `tests/Feature/HorizonAcessoTest.php`: sem a permissão, `/horizon` dá 403; com ela, 200.
- `tests/Unit/UserCanTest.php`:
  - `can('x')` com e sem a permissão;
  - `can(['a','b'], true)` exige as duas;
  - uma ability de Gate com model vai para o Gate.

## 2.3 Política de senha — 1 h

Regra única em `AppServiceProvider::boot()`:
```php
Password::defaults(fn () => Password::min(8)->letters()->numbers());
```
Aplicar `Password::defaults()` nos quatro pontos em que uma senha é definida:

| Onde | Hoje | Mudança |
| --- | --- | --- |
| `Config/UserController@store` (campo `senha`) | `required\|min:8`; na edição testa `$request->password`, que não existe, e por isso a senha nunca é validada ao editar | `[$id ? 'nullable' : 'required', Password::defaults()]` |
| `PasswordRequest` (troca no perfil) | `min:6` | `['required', 'confirmed', 'different:old_password', Password::defaults()]` |
| `ProfileController@password` | revalida inline com `min:6` e checa um `cpf` que o form não envia | remover a validação inline; o `PasswordRequest` basta |
| `Auth/ResetPasswordController` | regra do trait (`min:8`) | sobrescrever `rules()` com `Password::defaults()` |

- Traduções: `resources/lang/pt-BR/validation.php` não tem as chaves
  `password.letters`, `password.numbers` e `password.mixed`. Sem elas, a mensagem
  sai crua. Adicionar, ex.: "A senha deve conter ao menos uma letra."
- Sem troca obrigatória no primeiro acesso (Q17).
- De passagem: o `catch` do `UserController@store` e do
  `PermissoesController@store` devolve `success => true` com status 400. Passar
  a `false`.

**Testes** (`tests/Feature/PoliticaSenhaTest.php`), nos três fluxos (cadastro
pelo admin, troca no perfil, redefinição):
- "12345678" e "abcdefgh" são recusadas; "abc12345" é aceita.
- Editar usuário sem informar senha mantém a senha atual.

## 2.4 Usuário ativo/inativo — 1,5 h

**Migration** `add_ativo_to_users_table`: `boolean('ativo')->default(true)->after('password')`.
O `pessoa_fisica_id` fica para a fatia 3, que cria `pessoas_fisicas`.

**Login** (`LoginController`):
```php
protected function credentials(Request $request): array
{
    return [...$request->only('email', 'password'), 'ativo' => true];
}
```
O inativo recebe a mesma mensagem de credencial errada (`auth.failed`): a tela
não revela que a conta existe.

**Sessão aberta**: middleware `App\Http\Middleware\UsuarioAtivo` no grupo `web`.
- Usuário logado com `ativo = false` → `Auth::logout()`, invalida a sessão e
  redireciona ao login com "Seu acesso foi desativado.".
- Cobre também o login automático depois de redefinir a senha.

**Recuperação de senha**: o inativo pode pedir o link; o middleware barra a
entrada. Bloquear o envio exigiria sobrescrever o broker e não traz ganho.

**Tela de usuários**
- `config/users/form.blade.php`: chave "Ativo"; `$fillable` e `casts` do `User` recebem `ativo`.
- `UserController@index`: coluna "Ativo" (badge) e filtro ativos/inativos.
- `UserController@store` e `@delete`: o usuário não pode se desativar nem se excluir.
- Excluir continua com `softDeletes`. Desativar é o caminho normal; excluir
  serve para cadastro feito por engano.
- `UserFactory`: `ativo => true` e o estado `inativo()`.

**Testes** (`tests/Feature/UsuarioAtivoTest.php`)
- O inativo não entra.
- O usuário desativado com sessão aberta é desconectado na próxima requisição.
- O admin não consegue se desativar.

## 2.5 Auditoria: registro dos eventos — 2 h

**Instalação**
- `composer require spatie/laravel-activitylog:^4`.
- Publicar migrations e config.
- Em `config/activitylog.php`: `default_log_name => 'sistema'`; não apagar
  registros antigos (sem agendar `activitylog:clean`; retenção a decidir com a
  ABL PRIME na E6).

**Classe de apoio** `App\Support\Auditoria`, para que as fatias seguintes
registrem tudo do mesmo jeito:
```php
Auditoria::registrar(string $acao, ?Model $objeto = null, array $dados = []): void
// activity('sistema')->event($acao)->performedOn($objeto)->withProperties($dados + ['ip' => request()?->ip()])->log($acao)
```
`$acao` segue um vocabulário fixo, em `App\Support\Auditoria::ACOES`, para o filtro da tela.

**O que registrar na E1**

| Ação | Onde | Detalhe |
| --- | --- | --- |
| `login` | listener de `Illuminate\Auth\Events\Login` | causer = usuário; IP |
| `logout` | listener de `Logout` | idem |
| `login-falhou` | listener de `Failed` | sem causer; e-mail digitado e IP. **Nunca a senha** |
| `usuario-criado/alterado/excluido` | trait `LogsActivity` no `User` | `logOnly(['name','email','cpf','telefone','ativo'])`, `logOnlyDirty()`, `dontSubmitEmptyLogs()` |
| `usuario-senha-alterada` | `UserController@store` e `ProfileController@password` | só o fato, sem valor |
| `usuario-perfil-alterado` | `UserController@store` | `{antes: [...], depois: [...]}` (nomes de exibição) |
| `perfil-criado/alterado/excluido` | trait `LogsActivity` no `App\Role` | `display_name`, `description` |
| `perfil-permissoes-alteradas` | `PermissoesController@store` | `{incluidas: [...], removidas: [...]}`, comparando antes e depois do `sync` |
| `planilha-carregada` | fatia 5b | só o vocabulário entra agora |
| `sincronizacao-solicitada` | fatia 6 | idem |

- `LogsActivity` não vê `sync` e `attach` em tabelas pivô, por isso perfis de
  usuário e permissões de perfil são registrados à mão nos controllers.
- Os listeners ficam em `app/Listeners/RegistrarAcesso.php` (um método por
  evento), registrados no `EventServiceProvider`.
- `PermissoesController@store`: trocar `sync([])` + `attach()` por um único
  `sync($request->permissoes)`. O retorno (`attached`, `detached`) já dá a lista
  de incluídas e removidas.

**Testes** (`tests/Feature/AuditoriaRegistroTest.php`)
- Login, logout e falha geram uma linha cada. A falha não guarda a senha.
- Editar o nome de um usuário grava `old` e `new`. Salvar sem mudança não grava nada.
- Trocar a senha registra o fato, e nenhum campo de `properties` contém o hash.
- Alterar as permissões de um perfil registra as incluídas e as removidas.

## 2.6 Auditoria: tela — 2,5 h

`Configurações › Auditoria`. Rota `GET /auditoria` → `auditoria.index`, com
middleware `permission:auditoria-list`. Também `GET /auditoria/{id}` →
`auditoria.show` (modal).

**`App\Http\Controllers\Config\AuditoriaController`**, no padrão do `UserController`:
- `index(Builder $builder)`: no ajax, `DataTables::of(Activity::with('causer')->latest())`.
- Colunas:

  | Coluna | Conteúdo |
  | --- | --- |
  | Data | `created_at` em `d/m/Y H:i:s` |
  | Usuário | `causer->name`, ou "Sistema", ou o e-mail digitado (`login-falhou`) |
  | Ação | rótulo em português do vocabulário (`usuario-alterado` → "Usuário alterado") |
  | Objeto | tipo curto + identificação (ex.: "Usuário · Maria Souza", "Perfil · RH") |
  | Alterações | resumo: "e-mail, ativo" ou "3 permissões incluídas" |
  | — | link "Detalhes" |

- Filtros acima da tabela, enviados por `->ajax(['data' => 'function(d){ d.de = ...; }'])`:
  - período (de/até, padrão últimos 7 dias);
  - usuário (select com usuários, inclusive inativos);
  - ação (select com o vocabulário).
  O controller aplica com `when()`.
- `show($id)`: modal (`layouts.modal`) com a tabela campo / antes / depois e as
  listas de permissões incluídas e removidas.
- Sem edição nem exclusão. A tela é só leitura.
- O rótulo de cada ação e de cada tipo de objeto fica num só lugar
  (`Auditoria::rotulo()`), reaproveitado pela tela e pelo filtro.
- Item do menu: já previsto em `config/menu.php` (fatia 1). Aparece quando a rota existe.

**Testes** (`tests/Feature/AuditoriaTelaTest.php`)
- Sem `auditoria-list`: 403 na lista e no detalhe.
- Os filtros por usuário, ação e período devolvem só as linhas certas (requisição ajax).

---

## Teste transversal de permissões

`tests/Feature/PermissoesRotasTest.php`, com um helper em `tests/TestCase.php`:
```php
protected function usuarioCom(string ...$permissoes): User
// cria um perfil com nome aleatório (Str::random), liga as permissões e um usuário a ele
```
- Um `dataProvider` com cada rota protegida e sua permissão: `user.*`,
  `grupo.*`, `auditoria.*` e `/horizon`.
  - Usuário sem a permissão → 403.
  - Usuário com a permissão → 200.
- O perfil tem nome aleatório: isso prova que nenhuma checagem depende do nome
  do perfil (critério de aceite 1 da E1).
- As fatias 5b, 7 e 8 só acrescentam linhas ao `dataProvider`.

## Pronto quando

1. `php artisan migrate:fresh --seed` roda duas vezes seguidas sem erro.
2. Um perfil novo, criado pela tela com só `user-list`, enxerga apenas Configurações › Usuários e recebe 403 nas outras rotas.
3. Senhas fracas são recusadas nos três fluxos, com mensagem em português.
4. O usuário desativado não entra, e quem estava logado cai na próxima requisição.
5. Login, falha de login, edição de usuário e troca de permissões aparecem na tela de auditoria, com filtro.
6. `vendor/bin/phpunit` verde.

## Riscos

| Risco | Saída |
| --- | --- |
| A correção do `User::can()` muda o comportamento de alguma chamada existente | O teste `UserCanTest` cobre os três formatos usados hoje (string, array, array + `true`). Rodar `grep -rn "->can(\|@permission" app resources` e conferir cada chamada |
| Renomear `superadmin` → `administrador` quebra algum ponto que usa o nome | Hoje só o `UserCreateSeeder` (id fixo 1) depende disso; conferir com `grep -rn superadmin` |
| A tabela `activity_log` cresce rápido com login e falha de login | Índice em `created_at` (já vem na migration) e filtro padrão de 7 dias; retenção definida na E6 |
