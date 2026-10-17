# E1 · Fatia 1 — Setup, limpeza, e-mail e identidade visual · 12 h · semana 1

Detalha a fatia 1 do [plano da Entrega 1](entrega-1-base-uau-leitura.md).
Ao fim desta fatia:
- o repositório não tem mais restos do Breeze nem do template Argon;
- `vendor/bin/phpunit` passa sem falhas pré-existentes;
- a base de autoria (`RegistraAutoria` + `$table->auditoria()`) está pronta para a fatia 3;
- a recuperação de senha manda e-mail em português;
- login, topo e menu mostram a identidade da ABL PRIME.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 1.1 | Limpeza do Breeze e do Argon | 3 |
| 1.2 | Configuração (`.env.example`, `config/uau.php`, locale) | 1 |
| 1.3 | `RegistraAutoria` + macro `auditoria()` | 2 |
| 1.4 | E-mail e recuperação de senha | 2 |
| 1.5 | Identidade visual, login e menu | 4 |
| | **Total** | **12** |

Commits sugeridos (branch `e1/fatia-1-setup`, a partir de `develop`): um por tarefa.

---

## 1.1 Limpeza do Breeze e do Argon — 3 h

O `routes/web.php` usa `Auth::routes(['register' => false])` (laravel/ui).
Tudo o que o Breeze trouxe é código morto, e os testes dele são os que falham hoje.

**Apagar (controllers e requests)**
- `app/Http/Controllers/Auth/`: `AuthenticatedSessionController`,
  `ConfirmablePasswordController`, `EmailVerificationNotificationController`,
  `EmailVerificationPromptController`, `NewPasswordController`,
  `PasswordResetLinkController`, `RegisteredUserController`,
  `VerifyEmailController`, `RegisterController`.
- `app/Http/Requests/Auth/LoginRequest.php`: só o `AuthenticatedSessionController` usa.
- `app/Http/Requests/UserRequest.php`: importado no `UserController`, mas
  nunca usado (a validação está inline no `store`). Remover o `use` junto.
- `app/View/Components/AppLayout.php` e `GuestLayout.php`.
- `app/Traits/ModelTrait.php` (vazio; vira o `RegistraAutoria`, tarefa 1.3).

**Manter:** `LoginController`, `ForgotPasswordController`,
`ResetPasswordController`, `ConfirmPasswordController`, `VerificationController`.

**Apagar (views)**: conferir cada uma com `grep -rn "<nome>" resources app routes` antes.

| View | Motivo |
| --- | --- |
| `auth/register.blade.php` | cadastro público desligado |
| `auth/forgot-password`, `auth/reset-password`, `auth/confirm-password`, `auth/verify-email` | Breeze; o laravel/ui usa `auth/passwords/*` e `auth/verify` |
| `components/*` (`application-logo`, `auth-card`, `button`, `input`, `label`, `dropdown*`, `nav-link` etc.) | componentes Breeze, só usados pelas views acima |
| `layouts/guest.blade.php`, `layouts/navigation.blade.php` | Breeze |
| `layouts/navbars/*` | Argon; o layout usa `layouts/partials/_navbar` e `_sidebar` |
| `layouts/headers/cards.blade.php` | Argon, sem uso |
| `layouts/footers/guest.blade.php`, `layouts/footers/nav.blade.php` | conferir; `footers/auth` é usado em `profile/edit` |
| `pages/*`, `welcome.blade.php` | sem rota |

`layouts/headers/guest.blade.php` continua em uso por `auth/passwords/email`.
Sai na tarefa 1.5, quando essas views forem refeitas.

**Apagar (rotas e testes)**
- `routes/auth.php`.
- `tests/Feature/Auth/*`, `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php`.
- Em `routes/web.php`: o `Route::get('/', fn () => view('welcome'))` (sobrescrito
  pela rota `main` logo abaixo) e os blocos comentados.

**Dependências**
- `composer remove --dev laravel/breeze`.
- `app/Permission.php`: remover o `use Trebol\Entrust\EntrustPermission` (pacote inexistente).
- `EventServiceProvider`: remover o listener `Registered → SendEmailVerificationNotification` (não há cadastro público).

**Documentação**
- `docs/desenvolvimento.md`: tirar o parágrafo "Falhas pré-existentes".

**Pronto quando:** `vendor/bin/phpunit` roda sem erro (pode ficar sem testes
até a 1.3), `php artisan route:list` não mostra rota de `register` e
`php artisan view:cache` compila todas as views.

## 1.2 Configuração — 1 h

**`.env.example`**
```
APP_NAME="Gestão de Prestadores"
APP_LOCALE=pt-BR
APP_TIMEZONE=America/Sao_Paulo
APP_COR_PRIMARIA=#0d6efd

UAU_BASE_URL=https://<host>:<porta>/uauAPI/api/v1.0
UAU_LOGIN=
UAU_SENHA=
UAU_TOKEN_INTEGRACAO=
UAU_TIMEOUT=30
UAU_TOKEN_TTL=1800
UAU_FAKE=true
```
- O locale é `pt-BR`, com hífen, igual à pasta `resources/lang/pt-BR`. O
  `pt_BR` do plano da E1 não acharia as traduções.
- `config/app.php` hoje fixa `'locale' => 'pt-BR'` e `'timezone'`. Passar a ler
  `env('APP_LOCALE', 'pt-BR')` e `env('APP_TIMEZONE', 'America/Sao_Paulo')`;
  `fallback_locale` e `faker_locale` passam a `pt-BR`/`pt_BR`.
- Remover do `.env.example` as variáveis sem uso: `AWS_*`, `PUSHER_*`, `MIX_PUSHER_*`, `MEMCACHED_HOST`.

**`config/uau.php`**
```php
return [
    'base_url' => env('UAU_BASE_URL'),
    'login' => env('UAU_LOGIN'),
    'senha' => env('UAU_SENHA'),
    'token_integracao' => env('UAU_TOKEN_INTEGRACAO'),
    'timeout' => (int) env('UAU_TIMEOUT', 30),
    'token_ttl' => (int) env('UAU_TOKEN_TTL', 1800),
    'fake' => (bool) env('UAU_FAKE', true),
];
```
A ligação do `FakeUauClient` fica para a fatia 4. Aqui só o arquivo de config.

**`phpunit.xml`**: adicionar `<server name="UAU_FAKE" value="true"/>` e
`<server name="MAIL_MAILER" value="array"/>`.

## 1.3 `RegistraAutoria` + macro `auditoria()` — 2 h

**Macro de migration**: em `AppServiceProvider::boot()`:
```php
Blueprint::macro('auditoria', function () {
    $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $this->timestamps();
    $this->softDeletes();
});
```
- `nullable`: jobs da integração e seeders gravam sem usuário logado.
- A migration usa `$table->auditoria()` no lugar de `timestamps()` e `softDeletes()`.

**Trait `app/Models/Concerns/RegistraAutoria.php`**
```php
trait RegistraAutoria
{
    public static function bootRegistraAutoria(): void
    {
        static::creating(function (Model $model) {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });
        static::updating(fn (Model $model) => $model->updated_by = auth()->id() ?? $model->updated_by);
    }

    public function criadoPor(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function atualizadoPor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
```
- Num job (sem usuário), o `updating` mantém o último autor humano. Quem
  sincronizou fica visível pelo `sincronizado_em` e pelo `integracao_logs`.
- `deleted_by` não existe: quem exclui aparece na auditoria (fatia 2).

**Testes** (`tests/Unit/RegistraAutoriaTest.php`): usar uma tabela temporária
criada no `setUp` com `$table->auditoria()` e um model anônimo com o trait.
- Logado → `created_by` e `updated_by` preenchidos.
- Outro usuário edita → só `updated_by` muda.
- Sem usuário (simula job) → criar deixa `null`, editar não apaga o autor anterior.

## 1.4 E-mail e recuperação de senha — 2 h

**Serviço**: Brevo, plano gratuito (300 e-mails/dia). Conta criada com o e-mail da ABL PRIME.
```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=nao-responda@<dominio>
MAIL_FROM_NAME="${APP_NAME}"
```
- Credenciais só no `.env` do servidor. No `.env.example` ficam vazias, e o
  local usa `MAIL_MAILER=log` (o `docker-compose.yml` não tem Mailhog; trocar o
  `MAIL_HOST=mailhog` atual).
- SPF e DKIM do domínio remetente: os registros DNS vêm do painel do Brevo e
  quem os cria é a ABL PRIME. Isso depende do domínio (pendência externa); até
  lá, testar com o remetente verificado da conta.

**Notificação de redefinição**
- `User::sendPasswordResetNotification($token)` com a notificação
  `App\Notifications\RedefinirSenha` (estende a `ResetPassword` do Laravel).
  Assunto "Redefinição de senha — Gestão de Prestadores", textos em português,
  validade (60 min) no corpo.
- Conferir se o `resources/lang/pt-BR.json` cobre o resto do e-mail ("Hello!",
  "Regards", "If you're having trouble clicking..."). Completar o que faltar.
- `php artisan vendor:publish --tag=laravel-mail`: só se for preciso pôr o logo
  no cabeçalho do e-mail. Se não, manter o tema padrão.

**Views**: refazer `auth/passwords/email.blade.php` e `auth/passwords/reset.blade.php`
no mesmo layout da tela de login (tarefa 1.5). Com isso, sai
`layouts/headers/guest.blade.php`.

**Teste** (`tests/Feature/RecuperarSenhaTest.php`): `Notification::fake()`.
- Pedir o link envia `RedefinirSenha` ao usuário.
- E-mail inexistente não revela se a conta existe: mesma mensagem nos dois casos.
- O token redefine a senha.

**Pronto quando:** em homologação, um e-mail real chega à caixa de entrada
(não ao spam) e o link redefine a senha.

## 1.5 Identidade visual, login e menu — 4 h

**Layout `layouts/app.blade.php`**
- `<html lang="pt-BR">`, `<title>{{ $title ?? '' }} · {{ config('app.name') }}</title>`.
- Trocar as meta tags `title` ("AdminLTE v4 | Dashboard") e `author` ("ColorlibHQ").
  Favicon em `public/images/favicon.png`.
- Remover apexcharts e jsvectormap (CSS e JS): o dashboard não usa.
- Cor principal via variáveis CSS, num `<style>` logo depois do `adminlte.css`:
  ```html
  <style>:root{--bs-primary: {{ config('app.cor_primaria') }}; --bs-primary-rgb: {{ hex2rgb(config('app.cor_primaria')) }};}</style>
  ```
  - `config/app.php` ganha `'cor_primaria' => env('APP_COR_PRIMARIA', '#0d6efd')`.
  - `hex2rgb` entra em `app/Http/helpers.php`.
  - Conferir botões `.btn-primary` e `.text-bg-primary`: o AdminLTE 4 usa
    `--bs-btn-bg` nos botões, que talvez precise de override em `public/css/styles.css`.

**Logo**: `public/images/brand.png` (sidebar) e `public/images/logo-login.png`.
Sem o arquivo da ABL PRIME, o sidebar mostra `config('app.name')` em texto
(`brand-text`, hoje comentado) e o login mostra o nome em `<h1>`.

**Tela de login** (`auth/login.blade.php`): reescrever no padrão `login-page` /
`login-box` do AdminLTE 4, sem Argon (`ni ni-*`, `input-group-alternative`).
- Tirar o `value="admin@argon.com"` duplicado do campo de e-mail.
- Campos: e-mail, senha, "Lembrar-me" e o link "Esqueci minha senha".
- Mensagens de erro em português (`auth.failed` do `pt-BR/auth.php`).
- O `layouts/app.blade.php` já esconde topo e menu para visitante (`@auth`).
  Basta pôr a classe `login-page` no `<body>` quando `@guest`.

**Topo (`_navbar.blade.php`)**
- Remover o dropdown fictício de notificações ("15 Notifications"). Na fatia 8,
  o lugar recebe o indicador de falhas de integração.
- Traduzir "Profile" → "Meu perfil". "Logout" → "Sair" (via `__()`).

**Menu lateral** (`_sidebar.blade.php`), dirigido por config: as seções da E1
aparecem à medida que as fatias criam as rotas, sem editar o Blade de novo.
- `config/menu.php`:
  ```php
  return [
      ['titulo' => 'Pessoas', 'icone' => 'bi bi-people', 'itens' => [
          ['titulo' => 'Pessoas físicas', 'rota' => 'pessoa-fisica.index', 'permissao' => 'pessoa-list'],
          ['titulo' => 'Pessoas jurídicas', 'rota' => 'pessoa-juridica.index', 'permissao' => 'pessoa-list'],
          ['titulo' => 'Carregar planilha', 'rota' => 'importacao-pessoa.index', 'permissao' => 'pessoa-importar'],
      ]],
      ['titulo' => 'Empresas', 'icone' => 'bi bi-building', 'rota' => 'empresa.index', 'permissao' => 'empresa-list'],
      ['titulo' => 'Obras', 'icone' => 'bi bi-cone-striped', 'rota' => 'obra.index', 'permissao' => 'obra-list'],
      ['titulo' => 'Integrações', 'icone' => 'bi bi-arrow-left-right', 'rota' => 'integracao.index', 'permissao' => 'integracao-list'],
      ['titulo' => 'Configurações', 'icone' => 'bi bi-gear', 'itens' => [
          ['titulo' => 'Usuários', 'rota' => 'user.index', 'permissao' => 'user-list'],
          ['titulo' => 'Perfis', 'rota' => 'grupo.index', 'permissao' => 'grupo-list'],
          ['titulo' => 'Auditoria', 'rota' => 'auditoria.index', 'permissao' => 'auditoria-list'],
      ]],
  ];
  ```
- Um item aparece se `Route::has($rota)` e se `auth()->user()->can($permissao)`.
  Um grupo aparece se tiver ao menos um item visível. Item ativo: `request()->routeIs(<prefixo>.*)`.
- A filtragem fica numa classe `App\View\Menu` (método `itensVisiveis(User $u): array`),
  com teste de unidade. O Blade só percorre o resultado.
- O nome da rota de perfis continua `grupo.*` por compatibilidade; só o rótulo muda para "Perfis".
- Hoje o item "Grupos de Acesso" aparece para quem só tem `user-list`. A regra por
  item resolve isso.

**Teste** (`tests/Unit/MenuTest.php`):
- Usuário sem permissões vê só o Dashboard.
- Com `user-list` vê Configurações › Usuários, e não Perfis.
- Item com rota inexistente não aparece.

---

## Riscos

| Risco | Saída |
| --- | --- |
| Uma view apagada ainda é usada por algum `@include` | Rodar o `grep` antes de cada remoção e `php artisan view:cache` no fim da 1.1 |
| Logo e cor não chegam na semana 1 | Nome em texto + cor padrão; trocar depois são dois arquivos e uma variável |
| E-mail cai no spam sem SPF/DKIM | Não declarar a 1.4 pronta em homologação até o DNS estar configurado |
| `composer remove laravel/breeze` mexe em outras dependências do lock | Conferir o diff do `composer.lock`; só o Breeze deve sair |
