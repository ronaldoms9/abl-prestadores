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

Estas decisões valem para todos os planos. As marcadas **(confirmar)** divergem
da especificação e precisam de um "ok" antes da Entrega 1.

### D1 — Interface: manter a base do repositório **(confirmar)**

A spec prevê Filament (painel) + Livewire (portal). O repositório foi reiniciado
a partir de uma base que já tem login, CRUD de usuários e de grupos de acesso,
layout Bootstrap, DataTables server-side (yajra) e middleware `permission:`.

Recomendação: **seguir a base** — controller + `Yajra\DataTables\Html\Builder`
+ form Blade, como em `app/Http/Controllers/Config/UserController.php`. O
portal do colaborador usa o mesmo layout, com menu reduzido.

- A favor: nada a reescrever; o padrão já está provado no projeto.
- Contra: cada CRUD custa mais horas que no Filament. Os planos já contam com
  isso nas estimativas de tela.
- Se a escolha for Filament, só as fatias de "telas" mudam; ações, jobs,
  migrations e testes ficam iguais. Instalar o Filament na E1 custa ~6 h e
  devolve tempo a partir da E2.

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

## Lacunas encontradas na spec

Pontos que os planos precisaram resolver e que a spec não cobre. Cada um tem
uma proposta no plano indicado.

| Lacuna | Proposta | Plano |
| --- | --- | --- |
| "Gestor aprova férias da sua equipe" e "calendário por equipe", mas não há relação gestor ↔ colaborador | Campo `gestor_user_id` em `colaboradores`; equipe = colaboradores com o mesmo gestor | E2 |
| Colaborador solicita as próprias férias (E4), mas o portal só aparece na E5 | Criar a casca do portal (login do colaborador, "Minhas férias") na E4; a E5 acrescenta as notas | E4 |
| "Cálculo de valores finais proporcionais" no desligamento (E3) depende da calculadora da E4 | E3 entrega `RemuneracaoProporcional` (só remuneração); a E4 a reaproveita dentro da `CalculadoraFechamento` | E3, E4 |
| Destinatários dos alertas por e-mail não definidos | Tabela `configuracoes` (chave/valor) com os e-mails por tipo de alerta, editável pelo admin | E2 |
| Numeração de contratos | Sequencial por ano: `AAAA/NNNN`, aditivos `AAAA/NNNN-A01` | E3 |
| Testes de Breeze herdados falham (`docs/desenvolvimento.md`) | Remover Breeze e os testes órfãos na E1 | E1 |
