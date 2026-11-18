# E4 · Fatia 7 — Competência, conferência, ajustes e aprovação · 20 h

Detalha a fatia 7 do [plano da Entrega 4](entrega-4-ferias-fechamento.md). Usa a `CalculadoraFechamento` da fatia 6 e liga de verdade o `CompetenciaFechada` que as E2 e E3 deixaram preparado.

## Fluxo

1. **Abrir** (`fechamento-conferir`): informa o mês (não pode ser futuro; um mês só tem uma competência). O job `CalcularCompetencia` calcula todos os colaboradores que estiveram ativos em algum dia do mês e a competência passa a **em conferência**.
2. **Conferir** (`fechamento-conferir`): tabela por colaborador com dias, dias trabalhados, remuneração, benefícios, ajustes e líquido, com os avisos do cálculo (remuneração ou valor de benefício faltando). O detalhe mostra a memória de cálculo. O RH lança **ajustes avulsos** (crédito ou débito, com justificativa) e recalcula um colaborador ou todos. Mudanças feitas depois do cálculo (férias, ocorrências, feriados, benefícios) só entram no recálculo; a tela avisa isso.
3. **Aprovar** (`fechamento-aprovar`, financeiro): só em conferência. Com avisos pendentes, exige confirmação explícita. Grava o `snapshot` de cada fechamento (período, dias, itens, totais, regras usadas), muda os fechamentos para **aguardando nota**, a competência para **aprovada** e registra quem e quando.
4. **Imutável:** aprovada (ou fechada), a competência não recalcula e `CompetenciasAprovadas` passa a recusar lançamentos com data no mês (remuneração, benefício, ocorrência, feriado, férias, contrato por vínculo retroativo).
5. **Reabrir** (`fechamento-reabrir`, só admin): exige justificativa; só se nenhum fechamento passou de "aguardando nota" (na E5, título enviado ao UAU). Volta para em conferência e limpa o snapshot.

## Decisões

- **D7.1 — `em_conferencia` logo após o cálculo.** Não há etapa manual entre abrir e conferir; "aberta" existe só enquanto o job roda.
- **D7.2 — Recalcular substitui os itens.** Cada recálculo apaga e recria os itens do fechamento do colaborador (ajustes avulsos são fonte, não item); nada de histórico de cálculo antes da aprovação, a auditoria guarda quem recalculou e quando.
- **D7.3 — Ajuste avulso só em competência aberta ou em conferência.**
- **D7.4 — Auditoria:** abertura, recálculo, ajuste (criar e remover), aprovação e reabertura (com a justificativa).
- **D7.5 — Permissões novas:** `fechamento-list`, `fechamento-conferir`, `fechamento-aprovar`, `fechamento-reabrir`.
