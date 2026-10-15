# Entrega 1 — Base + integração UAU (leitura) · 96 h · semanas 1–3

Ao fim da E1, a ABL PRIME entra na homologação com seu usuário, carrega a
planilha dos prestadores atuais e vê essas pessoas, as empresas e as obras
trazidas do UAU, somente leitura, conciliadas todo dia. Cada chamada ao UAU
aparece na tela de monitoramento. Nada é gravado no UAU.

Decisões desta entrega fechadas em 2026-10-15 (README, D1–D7). Endpoints
confirmados no Swagger da instância da ABL PRIME (UAU API v1.0, 431 endpoints).

## Antes de começar (pendências externas)

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| URL **HTTPS** da UAU API de homologação | ABL PRIME | Ligar o UAU real (fim da semana 2) |
| Usuário técnico do UAU com login de **até 8 caracteres** (logins maiores autenticam como cliente) | ABL PRIME | Idem |
| **Token de integração** (`X-INTEGRATION-Authorization`), emitido pela Globaltec ou pela ABL PRIME | ABL PRIME | Idem |
| Ambiente de homologação do UAU (não ler produção) | ABL PRIME | Idem |
| VPS de homologação contratada (Hostinger KVM 2 ou equivalente) + acesso root | ABL PRIME | Fatia 9 |
| Domínio para o sistema e para o remetente de e-mail (registros SPF/DKIM) | ABL PRIME | Fatias 1 e 9 |
| Logo e cor principal | ABL PRIME | Fatia 1 (sem eles: nome em texto, cor padrão) |
| Planilha de CPFs/CNPJs dos prestadores atuais | ABL PRIME | Validação da fatia 5b |
| Significado dos códigos de `Status_obr` | ABL PRIME | Rótulo do status na tela de obras |
| Confirmar que a obra faz o papel de centro de custo | ABL PRIME (kickoff) | Escopo: a proposta cita "centros de custo" |

Até o UAU real estar acessível, tudo roda contra o `FakeUauClient` (fatia 4).

## Fatias e horas

| # | Fatia | Horas | Semana |
| --- | --- | --- | --- |
| 1 | Setup, limpeza, e-mail e identidade visual | 12 | 1 |
| 2 | Perfis, permissões e auditoria | 10 | 1 |
| 3 | Migrations e models | 8 | 1 |
| 4 | UauClient + fake | 16 | 2 |
| 5 | Importação de empresas, obras e pessoas | 13 | 2 |
| 5b | Carga por planilha | 5 | 2–3 |
| 6 | Agendamento + "Sincronizar agora" | 5 | 3 |
| 7 | Telas de consulta | 8 | 3 |
| 8 | Monitoramento das integrações | 6 | 3 |
| 9 | Homologação: VPS + `deploy.sh` | 10 | 3 (a VPS pode ser montada antes, assim que contratada) |
| | Folga | 3 | |
| | **Total** | **96** | 30 h + 32 h + 31 h |

Ordem: 3 depende de 1; 4 depende de 3 (logs); 5 e 5b dependem de 4; 6–8
dependem de 5. A fatia 9 é independente do código e deve começar assim que a
VPS existir.

---

### 1. Setup, limpeza, e-mail e identidade visual — 12 h

**Limpeza do Breeze**
- Remover controllers Breeze de `app/Http/Controllers/Auth/` que o `Auth::routes()` não usa: `AuthenticatedSessionController`, `ConfirmablePasswordController`, `EmailVerificationNotificationController`, `EmailVerificationPromptController`, `NewPasswordController`, `PasswordResetLinkController`, `RegisteredUserController`, `VerifyEmailController` e `RegisterController` (cadastro público desligado).
- Manter `LoginController`, `ForgotPasswordController`, `ResetPasswordController`, `ConfirmPasswordController`, `VerificationController` e `Auth::routes(['register' => false])`.
- Remover `routes/auth.php`, `tests/Feature/Auth/*`, `tests/Feature/ExampleTest.php`, as views Breeze sem rota (conferir uma a uma: `auth/register`, `auth/verify-email`, `auth/confirm-password` etc.), `resources/views/pages/*`, `welcome.blade.php` e o trait vazio `app/Traits/ModelTrait.php`.
- Retirar `laravel/breeze` do `require-dev`.
- Atualizar `docs/desenvolvimento.md` (a nota de "falhas pré-existentes" sai).

**Configuração**
- `.env.example`: `APP_NAME="Gestão de Prestadores"`, `APP_LOCALE=pt_BR`, `APP_TIMEZONE=America/Sao_Paulo`, bloco UAU e bloco de e-mail.
  ```
  UAU_BASE_URL=https://<host>:<porta>/uauAPI/api/v1.0
  UAU_LOGIN=
  UAU_SENHA=
  UAU_TOKEN_INTEGRACAO=
  UAU_TIMEOUT=30
  UAU_TOKEN_TTL=1800
  UAU_FAKE=true
  ```
- `config/uau.php` lendo essas variáveis. `UAU_FAKE=true` liga o `FakeUauClient` (fatia 4).
- Trait `App\Models\Concerns\RegistraAutoria` (preenche `created_by`/`updated_by` com o usuário logado) e macro de migration `$table->auditoria()` (os dois campos + `timestamps` + `softDeletes`).

**E-mail (Q8)**
- SMTP de um serviço com plano gratuito (ex.: Brevo, 300/dia); credenciais só no `.env` do servidor.
- Notificação de redefinição de senha traduzida (lucascudo/laravel-pt-br-localization já está no projeto).

**Identidade visual (Q9)**
- AdminLTE v4: logo e nome no topo e na tela de login, cor principal via variável CSS, `<html lang="pt-BR">`, título e meta tags sem "AdminLTE".
- Menu lateral com as seções da E1: Pessoas, Empresas, Obras, Integrações, Configurações (Usuários, Perfis, Auditoria). Itens exibidos conforme permissão (`@permission`).

### 2. Perfis, permissões e auditoria — 10 h

**Regra (README, D2):** o código só verifica permissões, nunca o nome de um perfil. Os perfis são livres: o admin edita, cria e exclui pela tela de grupos que já existe.

Permissões semeadas na E1 (`PermissionSeeder`, campo `module` por área):

| Permissão | Módulo | Uso |
| --- | --- | --- |
| `user-list/create/edit/delete` | Usuários | Já existem |
| `grupo-list/create/edit/delete` | Perfis | Já existem |
| `empresa-list` | Cadastros UAU | Ver empresas |
| `obra-list` | Cadastros UAU | Ver obras |
| `pessoa-list` | Pessoas | Ver pessoas |
| `pessoa-importar` | Pessoas | Carregar planilha, ver relatórios de carga |
| `integracao-list` | Integrações | Monitoramento |
| `integracao-sincronizar` | Integrações | "Sincronizar agora" |
| `integracao-reenviar` | Integrações | Reprocessar uma falha |
| `auditoria-list` | Configurações | Tela de auditoria |
| `horizon-acessar` | Configurações | Painel `/horizon` |

Perfis iniciais (seed, ajustáveis depois):

| Perfil | Permissões na E1 |
| --- | --- |
| Administrador | Todas |
| RH | `pessoa-list`, `pessoa-importar`, `empresa-list`, `obra-list` |
| Gestor | `pessoa-list`, `empresa-list`, `obra-list` |
| Financeiro | `pessoa-list`, `empresa-list`, `obra-list`, `integracao-list`, `integracao-sincronizar`, `integracao-reenviar` |
| Colaborador | nenhuma (portal na E5) |

- Corrigir no `routes/web.php` o middleware `permission:user-grupo|grupo-edit` (nome errado) para `grupo-create|grupo-edit`.
- `Gate::define('viewHorizon')` pela permissão `horizon-acessar`.
- **Senha (Q21):** `Password::min(8)->letters()->numbers()` no `UserRequest` e no `ProfileController@password`. O admin define a senha; não há troca obrigatória (Q17).
- `users.ativo`: usuário inativo não entra (checagem no `LoginController`).
- **Auditoria:** instalar spatie/laravel-activitylog. Registrar login, logout, falha de login, criação/alteração/exclusão de usuário e de perfil (incluindo permissões do perfil), carga de planilha e "Sincronizar agora". Tela `Configurações › Auditoria` (`auditoria-list`): data, usuário, ação, objeto, alterações; filtro por período, usuário e ação.

### 3. Migrations e models — 8 h

Todas com `$table->auditoria()` (fatia 1). Normalizar CPF/CNPJ como só dígitos.

| Tabela | Campos | Índices |
| --- | --- | --- |
| `empresas` | `uau_codigo` int, `cnpj`, `razao_social`, `inscricao_estadual`, `inscricao_municipal`, `endereco` json, `telefone`, `ativo`, `sincronizado_em` | único `uau_codigo` |
| `obras` | `empresa_id`, `uau_codigo` string, `descricao`, `status_uau` int, `tipo_uau`, `endereco`, `data_inicio`, `data_fim`, `ativo`, `sincronizado_em` | único (`empresa_id`, `uau_codigo`) |
| `pessoas_fisicas` | `uau_codigo` int, `cpf`, `nome`, `nascimento`, `email`, `telefone`, `endereco` json, `ativo`, `sincronizado_em` | único `cpf`; único `uau_codigo` |
| `pessoas_juridicas` | `uau_codigo` int, `cnpj`, `razao_social`, `nome_fantasia`, `regime_tributario` (nullable, preenchido na E2), `inscricao_municipal`, `inscricao_estadual`, `email`, `telefone`, `endereco` json, `ativo`, `sincronizado_em` | único `cnpj`; único `uau_codigo` |
| `uau_mapeamentos` | `tipo`, `chave_local`, `codigo_uau` | único (`tipo`, `chave_local`) — usado na E5 |
| `integracao_logs` | `direcao` (entrada/saida), `entidade_type`, `entidade_id`, `operacao`, `rota`, `chave_idempotencia`, `payload` json, `resposta` json, `http_status`, `status` (sucesso/erro/pendente), `tentativas`, `erro`, `duracao_ms`, `executado_em` | (`status`, `executado_em`); `chave_idempotencia` |
| `importacoes_pessoas` | `arquivo_path`, `nome_original`, `status` (pendente/processando/concluida/falhou), `total_linhas`, `resumo` json, `iniciada_em`, `concluida_em` | |
| `importacao_pessoa_linhas` | `importacao_pessoa_id`, `linha`, `cpf`, `cnpj`, `resultado_pf`, `resultado_pj` (importada/ja_existia/nao_encontrada/inativa/invalida), `mensagem` | `importacao_pessoa_id` |
| `vinculos_sugeridos` | `pessoa_fisica_id`, `pessoa_juridica_id`, `importacao_pessoa_id`, `utilizado_em` (nullable, marcado na E2) | único (`pessoa_fisica_id`, `pessoa_juridica_id`) |
| `users` (alteração) | `pessoa_fisica_id` nullable, `ativo` default true | |

Não há `centros_custo` (Q11): a obra faz o papel de centro de custo.

Models em `app/Models/` com `RegistraAutoria`, `SoftDeletes`, casts de json e datas, e factories para todas as tabelas.

### 4. UauClient + fake — 16 h

`app/Integrations/Uau/`.

- **Interface `UauGateway`** com métodos de negócio:
  - `empresasAtivas(): array` → `Empresa/ObterEmpresasAtivas`
  - `obrasAtivas(): array` → `Obras/ObterObrasAtivas`
  - `pessoaPorDocumento(string $cpfCnpj): ?array` → `Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus` com `status = 2` (ativas e inativas)
- **`UauClient implements UauGateway`**, sobre o HTTP client do Laravel:
  - Todas as rotas são `POST {UAU_BASE_URL}/{Controller}/{Acao}` com corpo JSON. A porta vai explícita na URL (o Swagger declara o host sem porta).
  - Autenticação: `Autenticador/AutenticarUsuario` com `{Login, Senha}` e header `X-INTEGRATION-Authorization`; a resposta é o token (string). Token em cache (`uau.token`, TTL `UAU_TOKEN_TTL`).
  - Toda chamada leva `X-INTEGRATION-Authorization` + `Authorization: <token>` (sem "Bearer"; confirmar no primeiro teste real). Resposta 401 → renova o token uma vez e repete.
  - Timeout `UAU_TIMEOUT`; nenhuma retentativa dentro do client (quem tenta de novo é o job).
  - Cada chamada grava uma linha em `integracao_logs` (rota, payload sem senha e sem tokens, resposta, status HTTP, duração).
  - Exceções: `UauIndisponivel` (rede, timeout, 5xx → job tenta de novo) e `UauRejeitou` (4xx → não tenta).
- **DTOs** em `Data/`: `EmpresaUau`, `ObraUau`, `PessoaUau` (com `tipo`: 0 = PF, 1 = PJ; `ativo`: `AtivoInativo` 0 = ativa).
- **`FakeUauClient implements UauGateway`**: lê `tests/Fixtures/uau/*.json` (montadas a partir dos modelos do Swagger) e registra as chamadas para os testes. Ligado por `UAU_FAKE=true` no `AppServiceProvider`.
- Comando `uau:ping`: autentica e chama `ConfigGerais/RetornarVersaoWS`, para validar credenciais no servidor.

### 5. Importação de empresas, obras e pessoas — 13 h

Jobs em `app/Integrations/Uau/Jobs/`, com `tries = 4`, `backoff = [60, 300, 900, 3600]`, `ShouldBeUnique` por tipo.

- **`ImportarEmpresas`**: upsert por `uau_codigo`; marca `sincronizado_em`; empresas que não vieram → `ativo = false` (nunca apagar).
- **`ImportarObras`**: uma chamada traz as obras de todas as empresas; liga pela `Empresa_obr`; obra de empresa desconhecida → importa a empresa antes (ou registra erro no log); mesma regra de inativação.
- **`ImportarPessoa(string $cpfCnpj)`**: consulta por documento; cria ou atualiza `pessoas_fisicas` ou `pessoas_juridicas` conforme `tipo`; pessoa inativa no UAU → `ativo = false`; devolve o resultado (importada/ja_existia/nao_encontrada/inativa) para a carga da planilha.
- **`ConciliarPessoas`**: despacha `ImportarPessoa` para cada pessoa já importada (só elas, Q2/Q13).
- Mappers em `Mappers/` (UAU → atributos locais), um por entidade, testados com as fixtures.
- Cadeia da conciliação diária: `ImportarEmpresas` → `ImportarObras` → `ConciliarPessoas` (`Bus::chain`).

### 5b. Carga por planilha — 5 h

- Tela `Pessoas › Carregar planilha` (`pessoa-importar`): upload CSV ou XLSX (leitura com maatwebsite/excel, que a E6 já usaria), modelo para download com as colunas `cpf` e `cnpj`.
- Validação na hora do upload: cabeçalho, documentos com dígito verificador (rules `Cpf` e `Cnpj` criadas aqui e reaproveitadas na E2), linhas vazias.
- Job `ProcessarImportacaoPessoas`: para cada linha, `ImportarPessoa` do CPF e do CNPJ; grava o resultado em `importacao_pessoa_linhas`; se ambos foram importados, cria o `vinculo_sugerido`.
- Tela de resultado: contadores por resultado e a lista das linhas com problema; histórico de cargas. Recarregar a planilha é seguro (idempotente).
- A busca avulsa por CPF/CNPJ fica para a E2 (Q22).

### 6. Agendamento + "Sincronizar agora" — 5 h

- `app/Console/Kernel.php`: conciliação completa diária às 05:00 (`ImportarEmpresas` → `ImportarObras` → `ConciliarPessoas`). Cron do scheduler no servidor (fatia 9).
- Botão "Sincronizar agora" na tela de monitoramento (`integracao-sincronizar`): despacha a mesma cadeia; bloqueado enquanto uma sincronização estiver rodando (`ShouldBeUnique`).
- Sem webhook: o UAU da ABL PRIME não oferece webhook de saída (registrar no roteiro de homologação).

### 7. Telas de consulta — 8 h

Padrão da base: controller + `Yajra\DataTables\Html\Builder` + Blade, como `Config/UserController`. Sem botões de edição.

- **Pessoas físicas:** CPF (máscara), nome, e-mail, ativo, sincronizado em. Detalhe com endereço e as PJs ligadas por vínculo sugerido.
- **Pessoas jurídicas:** CNPJ, razão social, nome fantasia, ativo, sincronizado em.
- **Empresas:** código UAU, CNPJ, razão social, ativo; detalhe com as obras.
- **Obras:** empresa, código, descrição, status UAU, datas, ativo.
- Filtro ativos/inativos; busca por documento com ou sem máscara.

### 8. Monitoramento das integrações — 6 h

- Lista de `integracao_logs` (`integracao-list`): filtros por status, operação, direção e período.
- Detalhe com payload e resposta formatados.
- "Reprocessar" numa falha (`integracao-reenviar`): despacha de novo a importação daquela entidade.
- Indicador no topo da tela: falhas nas últimas 24 h e data da última conciliação bem-sucedida.

### 9. Homologação: VPS + `deploy.sh` — 10 h

**Servidor (instalação direta, Q4):** Ubuntu 24.04, Nginx, PHP 8.4-FPM (extensões do `.docker/Dockerfile`), MySQL 8, Redis, Supervisor (Horizon), Certbot (HTTPS), cron do scheduler, usuário `deploy` sem root, firewall só 22/80/443. Roteiro em `docs/infra/homologacao.md`, para a E6 repetir em produção.

**`deploy.sh`** (versionado, rodado à mão por SSH, Q5/Q16):
```
git fetch && git checkout develop && git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run production
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan horizon:terminate
```
Aborta no primeiro erro (`set -e`). Testes rodam localmente antes do deploy (`vendor/bin/phpunit`); não há CI.

**Dados de homologação:** seed dos perfis e de um usuário por perfil; `UAU_FAKE=false` quando houver HTTPS e token; primeira conciliação e carga da planilha da ABL PRIME.

---

## Testes obrigatórios (PHPUnit, SQLite em memória)

- **UauClient** (com `Http::fake`): autenticação envia os dois headers; token em cache; 401 renova uma vez; log gravado sem senha nem tokens; 4xx → `UauRejeitou`, 5xx/timeout → `UauIndisponivel`.
- **Mappers:** cada fixture do UAU → atributos esperados (PF, PJ, empresa, obra).
- **Importação:** cria, atualiza, inativa o que sumiu, não duplica ao rodar duas vezes; obra de empresa desconhecida.
- **Planilha:** cabeçalho inválido recusado; documento inválido marcado; cada resultado (importada, já existia, não encontrada, inativa); vínculo sugerido criado; recarga idempotente.
- **Permissões:** cada rota recusa quem não tem a permissão; nenhuma checagem por nome de perfil (teste com um perfil novo criado na hora).
- **Senha:** "12345678" e "abcdefgh" recusadas; "abc12345" aceita. Usuário inativo não entra.

## Critério de aceite

1. Cada usuário de teste entra e vê só o menu das suas permissões; um perfil novo criado pelo admin funciona sem mudança de código.
2. Empresas e obras ativas batem com o UAU de homologação (amostra escolhida pela ABL PRIME).
3. A planilha dos prestadores atuais é carregada; o relatório mostra o resultado de cada linha, e as pessoas importadas batem com o UAU.
4. Uma pessoa alterada no UAU aparece alterada no dia seguinte, ou na hora com "Sincronizar agora".
5. Uma falha simulada (UAU fora do ar) aparece no monitoramento e se resolve sozinha na próxima tentativa.
6. Recuperação de senha por e-mail funciona.

Roteiro de homologação deve dizer: sem webhook (UAU não oferece); obra no lugar de centro de custo; busca avulsa só na E2.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| API só em HTTP no fim da semana 2 | Senha e token trafegam abertos | Não ligar a homologação em HTTP; entregar com o fake e ligar quando houver HTTPS |
| Token de integração demora (depende da Globaltec) | Sem chamada real | Mesmo plano do item acima; cobrar no kickoff |
| `ConsultarDadosPessoaPorCpfCnpjEStatus` devolve mais de uma pessoa para o mesmo documento | Importação ambígua | Escolher a ativa mais recente e marcar a linha para revisão |
| Formato do token (`Authorization` com ou sem "Bearer") diferente do inferido | 401 em tudo | Validar com `uau:ping` no primeiro acesso |
| Instalação da VPS esbarra em limitação do provedor | Fatia 9 estoura | Usar a folga de 3 h |
