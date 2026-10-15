# Entrega 1 — Base + integração UAU (leitura) · 96 h · semanas 1–3

Ao fim da E1, a ABL PRIME entra na homologação com seu perfil, vê pessoas PF/PJ,
empresas, obras e centros de custo importados do UAU (somente leitura) e
acompanha cada sincronização na tela de monitoramento. Nenhum dado é gravado no
UAU.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| URL HTTPS da UAU API de homologação + usuário técnico | ABL PRIME | Fatias 4–6 |
| Endpoints de autenticação, empresas/obras, pessoas e centros de custo mapeados no Swagger (spec 5.3) | Nós, com acesso | Fatias 4–5 |
| Centro de custo existe como entidade própria no UAU? | ABL PRIME / Swagger | Tabela `centros_custo` |
| Webhook do UAU disponível no ambiente da ABL PRIME? | ABL PRIME | Fatia 6 (parte do webhook) |
| Servidor de homologação contratado | ABL PRIME | Fatia 9 |

Sem a API em HTTPS, as fatias 1–3 e 7–8 andam com o `FakeUauClient` (fatia 4)
e a integração real fica para a última semana.

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Setup e limpeza da base | 12 |
| 2 | Perfis, permissões e auditoria | 10 |
| 3 | Migrations e models da spec 3.1 | 8 |
| 4 | UauClient | 16 |
| 5 | Jobs de importação | 20 |
| 6 | Agendamento, "sincronizar agora" e webhook | 8 |
| 7 | Telas de consulta | 10 |
| 8 | Monitoramento das integrações | 6 |
| 9 | Homologação | 6 |
| | **Total** | **96** |

### 1. Setup e limpeza da base — 12 h

- Remover Breeze: controllers `Auth/*` não usados (`AuthenticatedSessionController`, `RegisteredUserController` etc.), `routes/auth.php`, `tests/Feature/Auth/*`, `tests/Feature/ExampleTest.php`. Manter `Auth::routes(['register' => false])` e o `LoginController`.
- Remover views de demo (`resources/views/pages/*`, `welcome.blade.php`) e o `ModelTrait` vazio.
- `.env.example`: `APP_NAME="Gestão de Prestadores"`, `APP_LOCALE=pt_BR`, `APP_TIMEZONE=America/Sao_Paulo`, bloco `UAU_*` (base_url, usuario, senha, timeout).
- `config/uau.php` lendo essas variáveis.
- Instalar Pint e Larastan (nível 5 para começar); GitHub Actions com Pint, Larastan e PHPUnit.
- Trait `RegistraAutoria` (`created_by`/`updated_by`) e helper de migration para os campos de auditoria + soft delete.
- Menu lateral com as seções do sistema (itens das próximas entregas ocultos).

### 2. Perfis, permissões e auditoria — 10 h

- Seed dos perfis `admin`, `rh`, `gestor`, `financeiro`, `colaborador` (D2).
- Seed das permissões da spec seção 6 com `module` por área. Nesta entrega: `pessoa-list`, `empresa-list`, `obra-list`, `integracao-list`, `integracao-reenviar`, `integracao-sincronizar`, além das de usuário e grupo que já existem.
- Instalar spatie/laravel-activitylog; registrar login, alteração de usuário/perfil e disparo manual de sincronização.
- Tela de auditoria simples (admin): quem, quando, o quê, filtro por período e usuário.

### 3. Migrations e models — 8 h

Tabelas da spec 3.1: `empresas`, `obras`, `centros_custo`, `pessoas_fisicas`, `pessoas_juridicas`, `uau_mapeamentos`, `integracao_logs` e `users.pessoa_fisica_id` + `users.ativo`.

- `uau_codigo` único por tabela (em `obras` e `centros_custo`, único com `empresa_id`).
- `ativo` boolean em todas as tabelas importadas (inativação em vez de exclusão).
- `integracao_logs`: índice em (`status`, `executado_em`) e em `chave_idempotencia`.
- Factories para todas as tabelas.

### 4. UauClient — 16 h

`app/Integrations/Uau/UauClient.php`, sobre o HTTP client do Laravel.

- Autenticação com usuário técnico; token em cache até expirar; renovação automática em 401.
- Timeout configurável; nenhuma retentativa dentro da chamada (quem tenta de novo é o job).
- Cada chamada grava uma linha em `integracao_logs` (payload sem a senha, resposta, duração, status).
- Exceções tipadas: `UauIndisponivel` (rede/5xx → tentar de novo), `UauRejeitou` (4xx → não tentar).
- Interface `UauGateway` + `FakeUauClient` com respostas em `tests/Fixtures/uau/*.json`, usado nos testes e em desenvolvimento sem acesso.

### 5. Jobs de importação — 20 h

`ImportarEmpresas`, `ImportarObras`, `ImportarCentrosCusto`, `ImportarPessoasFisicas`, `ImportarPessoasJuridicas`.

- Paginação da API, upsert por `uau_codigo`, `sincronizado_em = now()`.
- Registros que sumiram do UAU: `ativo = false`, nunca apagar.
- Mappers em `Integrations/Uau/Mappers`, um por entidade, com teste a partir das fixtures.
- Job com `tries`, `backoff` crescente (60, 300, 900, 3600 s) e `ShouldBeUnique` por tipo de importação.
- Ordem: empresas → obras/centros de custo → PJ → PF (encadeados com `Bus::chain`).
- Normalizar CPF/CNPJ (só dígitos) antes de gravar.

### 6. Agendamento, "sincronizar agora" e webhook — 8 h

- `app/Console/Kernel.php`: empresas/obras/CC diário às 05:00; pessoas a cada hora.
- Botão "Sincronizar agora" (permissão `integracao-sincronizar`) que despacha a cadeia e mostra o andamento pelo log.
- Webhook: rota `POST /webhooks/uau` fora do CSRF, verificação de assinatura ou token, grava o evento e despacha a importação da entidade afetada. Se o UAU da ABL PRIME não tiver webhook, a rota fica pronta e desligada por config.

### 7. Telas de consulta — 10 h

Listas com DataTables server-side e tela de detalhe, sem botões de edição:

- Pessoas físicas (CPF, nome, e-mail, sincronizado em).
- Pessoas jurídicas (CNPJ, razão social, regime).
- Empresas, com suas obras e centros de custo.
- Filtro "ativos / inativos" e busca por documento com ou sem máscara.

### 8. Monitoramento das integrações — 6 h

- Lista de `integracao_logs` com filtros por status, entidade, direção e período.
- Detalhe com payload e resposta formatados.
- Botão "Reenviar" para falhas de saída (permissão `integracao-reenviar`); nesta entrega só há entradas, então o botão reprocessa a importação.
- Indicador no topo: falhas nas últimas 24 h.

### 9. Homologação — 6 h

- Deploy automático de `develop` no servidor de homologação (script com `migrate --force`, `config:cache`, reinício do Horizon).
- Horizon protegido por permissão de admin.
- Seed de usuários de teste, um por perfil.
- Roteiro de validação para a ABL PRIME (abaixo).

## Testes obrigatórios

- UauClient: token em cache, renovação em 401, log gravado sem senha, exceção certa para 4xx e 5xx.
- Cada mapper: fixture do UAU → atributos esperados.
- Importação: cria, atualiza, inativa o que sumiu, não duplica ao rodar duas vezes.
- Webhook: assinatura inválida é recusada.
- Permissões: cada perfil acessa só o que a matriz permite (teste por rota).

## Critério de aceite

1. Cada perfil entra com seu usuário e vê só o menu permitido.
2. Pessoas, empresas, obras e centros de custo batem com o UAU de homologação (amostra de 20 registros escolhida pela ABL PRIME).
3. Uma pessoa alterada no UAU aparece alterada no sistema em até 1 h, ou na hora com "Sincronizar agora".
4. Uma falha simulada (UAU fora do ar) aparece no monitoramento e se resolve sozinha na próxima tentativa.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| API só em HTTP até o fim da E1 | Senha do UAU trafega aberta | Não ligar a homologação à API em HTTP; entregar com o fake e ligar quando houver HTTPS |
| Swagger sem endpoint de centro de custo | Tabela fica vazia | Cadastro manual de centros de custo na E2, ou de-para por obra |
| Volume de pessoas alto e API sem filtro por data | Importação horária lenta | Importação horária só de alterados (se a API filtrar); senão, completa diária + webhook |
