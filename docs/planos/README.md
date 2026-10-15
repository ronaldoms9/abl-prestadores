# Planos de implementação — Gestão de Prestadores ABL PRIME

Seis planos, um por entrega da proposta comercial (584 h, ~17 semanas). Cada
plano quebra a entrega em fatias com horas que somam o valor contratado, lista
o que precisa estar decidido antes de começar e define o critério de aceite da
homologação.

Fontes:
- [Proposta comercial](https://claude.ai/artifact/DUYdrGSKfuipS8Mo5wtscv) — escopo e valores (o contrato).
- [Especificação técnica](https://claude.ai/artifact/BU1tyS1dfn1KoaBFe47wG9) — modelo de dados, regras e backlog. As seções citadas nos planos (ex.: "spec 3.2") são dela.

| Entrega | Plano | Horas | Semanas | Bloqueada por |
| --- | --- | --- | --- | --- |
| 1 — Base + UAU (leitura) | [entrega-1-base-uau-leitura.md](entrega-1-base-uau-leitura.md) | 96 | 1–3 | Acesso à UAU API de homologação em HTTPS; endpoints mapeados |
| 2 — Cadastros completos | [entrega-2-cadastros.md](entrega-2-cadastros.md) | 104 | 4–6 | Endpoint de gravação de pessoa; matriz de permissões validada |
| 3 — Contratos e benefícios | [entrega-3-contratos-beneficios.md](entrega-3-contratos-beneficios.md) | 112 | 7–9 | Modelos de contrato/aditivo/distrato atuais; regra do benefício fixo |
| 4 — Férias e fechamento | [entrega-4-ferias-fechamento.md](entrega-4-ferias-fechamento.md) | 104 | 10–12 | Regras de proporcionalidade, sábado e férias PJ |
| 5 — Notas fiscais e contas a pagar | [entrega-5-notas-contas-pagar.md](entrega-5-notas-contas-pagar.md) | 88 | 13–15 | Municípios emissores; endpoint de título; regra de vencimento; de-para |
| 6 — Relatórios e implantação | [entrega-6-relatorios-implantacao.md](entrega-6-relatorios-implantacao.md) | 80 | 16–17 | Servidor de produção contratado; UAU de produção liberado |

As entregas são sequenciais: cada uma usa as tabelas e as ações da anterior. A
única exceção possível é adiantar o leitor de XML (E5) durante a E4, se os
municípios forem definidos cedo.

## Decisões transversais

Estas decisões valem para todos os planos. D1 diverge da especificação técnica
por escolha deliberada (decidido em 2026-10-15).

### D1 — Interface: manter a base do repositório (decidido)

A spec prevê Filament (painel) + Livewire (portal). O repositório já tem login,
CRUD de usuários e de grupos de acesso, layout Bootstrap, DataTables
server-side (yajra) e middleware `permission:`, e o projeto **segue essa base**:
controller + `Yajra\DataTables\Html\Builder` + form Blade, como em
`app/Http/Controllers/Config/UserController.php`. O portal do colaborador
(E5) usa o mesmo layout em Blade, com menu reduzido — sem Livewire nem
Filament. As estimativas de tela dos planos já contam com esse padrão.

### D2 — Perfis e permissões: usar as tabelas existentes

A base já tem `roles`, `permissions`, `permission_role`, `role_user`, o
middleware `permission:` e a diretiva `@permission`. Não instalar
spatie/laravel-permission. Os cinco perfis da spec seção 6 (admin, rh, gestor,
financeiro, colaborador) viram registros em `roles`, semeados pelo
`PermissionSeeder`. Regras do tipo "só a sua equipe" ou "só as próprias" ficam
em Policies, não em permissões.

### D3 — Pacotes

| Uso | Pacote | Entra na |
| --- | --- | --- |
| Auditoria | spatie/laravel-activitylog | E1 |
| Qualidade | laravel/pint, larastan/larastan | E1 |
| Testes | PHPUnit (já instalado; não migrar para Pest) | — |
| PDF | barryvdh/laravel-dompdf | E3 |
| Excel | maatwebsite/excel | E6 |
| Erros em produção | Sentry (sentry/sentry-laravel) | E6 |

### Alertas por e-mail

Vão a todos os usuários ativos do perfil responsável (ex.:
documentos e qualificações → RH; integrações → financeiro). Não há lista de
destinatários para manter.

### D4 — Organização do código

```
app/
  Actions/<Dominio>/        # regras: ReajustarRemuneracao, AprovarFechamento...
  Integrations/Uau/
    UauClient.php           # HTTP, token, retentativa, log
    Data/                   # DTOs das respostas
    Mappers/                # UAU <-> modelos locais
    Jobs/                   # ImportarEmpresas, EnviarPessoa, EnviarTituloPagar...
    Webhooks/               # controller + verificação
  Services/Fechamento/      # CalendarioTrabalho, CalculadoraFechamento
  Services/NotaFiscal/      # leitores de XML por layout
  Http/Controllers/<Modulo>/
  Models/
  Policies/
```

As ações são chamadas pelos controllers, pelo portal e pelos jobs. Toda regra
de valor tem teste de unidade na ação, sem passar pela tela.

### D5 — Regras que valem em todas as entregas (spec seção 1)

- Valor nunca é sobrescrito: reajuste encerra a linha vigente e cria outra.
- Fechamento aprovado é imutável (snapshot).
- Nenhuma tela chama o UAU; só jobs, com linha em `integracao_logs`.
- Todo envio ao UAU tem chave de idempotência.
- Toda tabela de negócio tem `created_by`, `updated_by` e `deleted_at`. Um trait
  `RegistraAutoria` preenche os dois primeiros (substitui o `ModelTrait` vazio).

### D6 — Git, CI e ambientes

- Branches: `main` (produção), `develop` (homologação), uma branch por tarefa.
- CI (GitHub Actions) a cada push: Pint, Larastan e PHPUnit com SQLite em
  memória. Deploy em homologação ao integrar em `develop`.
- Credenciais do UAU só no `.env` do servidor.

## Decisões sobre as lacunas da spec

Pontos que a spec não cobria, decididos em 2026-10-15.

| Lacuna | Decisão | Plano |
| --- | --- | --- |
| "Gestor aprova férias da sua equipe" e "calendário por equipe", sem relação gestor ↔ colaborador | Entidade `equipes` (ver D7) | E2, E4 |
| Colaborador solicita as próprias férias, mas o portal só aparece na E5 | Na E4, só RH e gestor solicitam; "Minhas férias" entra no portal da E5. Avisar no roteiro de homologação da E4 | E4, E5 |
| "Cálculo de valores finais proporcionais" no desligamento (E3) depende da calculadora da E4 | A prévia da E3 calcula remuneração proporcional + benefício fixo; benefícios por dia entram no fechamento da E4. Avisar a ABL PRIME | E3, E4 |
| Destinatários dos alertas por e-mail | Por perfil: o alerta vai a todos os usuários ativos do perfil responsável; nota não enviada vai também ao colaborador | E2 |
| Numeração de contratos | `AAAA/NNNN`, aditivo `AAAA/NNNN-A01`, distrato `AAAA/NNNN-D`; prefixo e reinício anual configuráveis | E3 |
| Testes de Breeze herdados falham | Remover controllers Breeze não usados, `routes/auth.php` e testes órfãos; manter `Auth::routes(['register' => false])` e o `LoginController` | E1 |

### D7 — Equipes e aprovação de férias

- `equipes`: independentes da obra (o filtro "por obra" do calendário usa a obra do colaborador).
- `equipe_gestores` (equipe, user, início, fim): uma equipe pode ter vários gestores, todos com perfil `gestor`; um gestor pode gerir várias equipes; histórico com vigência.
- `colaborador_equipes` (colaborador, equipe, início, fim): um colaborador pode estar em várias equipes, ou em nenhuma; histórico com vigência.
- Níveis de aprovação configuráveis pelo admin, cada um do tipo "gestor da equipe" ou "perfil X". Padrão: 1. gestor da equipe → 2. RH.
- Aprovadores do nível "gestor da equipe": qualquer gestor vigente de qualquer equipe vigente do colaborador, **no dia da decisão**. Todos recebem o e-mail; o primeiro a decidir encerra o nível. Se o colaborador muda de equipe com solicitação pendente, ela passa para os novos gestores.
- Nos níveis por perfil, qualquer usuário do perfil decide.
- Colaborador sem equipe vigente: o nível "gestor da equipe" é pulado.
- Ninguém aprova as próprias férias: o solicitante sai da lista do nível; se não sobrar aprovador, a solicitação sobe ao nível seguinte; se era o último, vai ao admin.
- Visibilidade do gestor: colaboradores com vínculo vigente hoje em equipes que ele gere hoje.
