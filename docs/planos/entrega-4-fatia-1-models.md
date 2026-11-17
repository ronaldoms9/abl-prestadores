# E4 · Fatia 1 — Migrations e models · 6 h

Implementada na branch `e3/fatia-1-models` (junto com a E3). Detalha a fatia 1 do [plano da Entrega 4](entrega-4-ferias-fechamento.md) e a seção 3.4 da especificação.

## O que foi feito

- **Tabelas da spec 3.4:** `ferias_periodos`, `ferias_solicitacoes`, `ferias_aprovacoes`, `feriados`, `ocorrencias`, `competencias`, `fechamentos`, `fechamento_itens` e `ajustes_avulsos`. Acréscimos do plano: `ferias_niveis`, `ferias_solicitacoes.nivel_atual`, `ferias_aprovacoes.escalada` e `motivo_escalada`.
- **Models e factories** de todas, com autoria e exclusão lógica; `Auditavel` em solicitação, feriado, ocorrência e ajuste (o ajuste não audita a justificativa).
- **Enums:** `StatusSolicitacaoFerias`, `DecisaoFerias` (aprovada, recusada, pulada), `TipoAprovadorFerias`, `AbrangenciaFeriado`, `TipoOcorrencia`, `StatusCompetencia`, `StatusFechamento`, `TipoItemFechamento` e `TipoAjuste`.
- **Fluxo padrão de aprovação** (`FeriasNivelSeeder`): 1. gestor da equipe; 2. quem tem `ferias-aprovar-rh`. Só cria quando não há nenhum nível.
- **Permissões** (seis): `ferias-list`, `ferias-solicitar`, `ferias-aprovar-rh`, `ferias-aprovar-qualquer`, `ferias-niveis-configurar` e `alerta-ferias`. RH recebe `ferias-list`, `ferias-solicitar`, `ferias-aprovar-rh` e `alerta-ferias`; Gestor, `ferias-list` e `ferias-solicitar`; Financeiro, `ferias-list`. `ferias-aprovar-qualquer` e `ferias-niveis-configurar` ficam com o administrador.
- Apelidos de morph para as origens do fechamento (`remuneracao`, `colaborador-beneficio`, `beneficio-valor`, `ajuste-avulso`) e para os demais models novos.

## Decisões

- **D1.1 — Valores em `decimal(12,2)`, como no resto do sistema.** O cálculo roda em centavos inteiros e só converte para gravar.
- **D1.2 — `competencias.ano_mes` é texto "AAAA-MM"**, único.
- **D1.3 — `StatusFechamento` começa com dois valores** (`calculado` e `aguardando_nota`, este último a partir da aprovação, E4 fatia 7). A E5 acrescenta os da nota fiscal.
- **D1.4 — Duas permissões a mais que o plano:** `ferias-list` (ver períodos e saldo) e `alerta-ferias` (alerta da fatia 2).
