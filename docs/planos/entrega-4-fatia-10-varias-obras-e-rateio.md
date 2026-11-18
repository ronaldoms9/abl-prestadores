# E4 · Fatia 10 — Colaborador em várias obras e rateio · 18 h

Escopo novo, pedido em 18/11/2026, fora das 584 h da proposta (horas a combinar com a ABL PRIME). Hoje o colaborador tem uma obra (`colaboradores.obra_id`). Um colaborador pode atuar em várias obras ao mesmo tempo, e o que ele recebe deve ser **rateado** entre elas.

## Regras (padrão assumido até a resposta da ABL PRIME, P25)

- **Rateio** (`colaborador_obras`): colaborador, obra, `percentual` (0,01 a 100,00) e vigência (início, fim exclusivo, como `TemVigencia`). Em qualquer data, os percentuais vigentes de um colaborador somam exatamente 100.
- **Obra principal:** `colaboradores.obra_id` continua existindo e passa a ser a obra de maior percentual vigente hoje (empate: a de menor id). É mantida pela ação; serve às telas e filtros que só precisam de uma obra.
- **Sem rateio cadastrado:** vale a obra do colaborador com 100%; sem obra, o valor vai para "Sem obra". Nada muda para os dados atuais.
- **Definir o rateio:** a ação `DefinirRateioObras` recebe a lista (obra e percentual) e a data de início. Encerra as linhas vigentes na véspera dessa data e cria as novas. Recusa obra repetida, soma diferente de 100, data anterior à admissão e competência já aprovada naquele mês (mesma regra dos demais lançamentos).
- **Fechamento rateado** (`fechamento_rateios`): para cada fechamento, uma linha por obra com percentual efetivo, remuneração, benefícios, ajustes e total líquido, em centavos. O percentual efetivo pesa pelos **dias corridos do mês** em que cada rateio esteve vigente (dentro do período do colaborador). O que sobra de centavos vai à obra de maior parcela, para a soma bater com o fechamento. A linha é gravada na hora do cálculo e congelada na aprovação, como os itens.
- **Ajuste avulso:** rateado como o resto, no padrão. Obra específica por ajuste fica como melhoria futura.
- **Feriado da obra:** vale o feriado de qualquer obra em que o colaborador esteja vigente (padrão simples; a refinar se a ABL PRIME pedir).
- **Relatórios e painel:** o relatório Custo por obra soma `fechamento_rateios`, e o filtro por obra enxerga o colaborador em qualquer uma das suas obras. O painel conta o colaborador em cada obra em que está vigente. Os demais relatórios que filtram por obra usam o mesmo escopo `naObra`.
- **Título no UAU (E5 fatia 6):** a forma do rateio no UAU é pergunta à ABL PRIME (P27); nada é enviado nesta fatia.

## Decisões

- **D10.1 — Percentual fixo com vigência**, e não proporcional a horas ou dias trabalhados em cada obra: é o que o sistema já sabe fazer (vigência) e não exige apontamento novo. Se a ABL PRIME quiser por horas, o ponto de troca é `RateioDoFechamento`.
- **D10.2 — Rateio calculado no fechamento, não no relatório.** O custo por obra de um mês aprovado não muda se o cadastro mudar depois.
- **D10.3 — Manter `obra_id`.** Evita reescrever filtros, calendário e conflitos de férias; passa a ser "obra principal".

## Tarefas

1. Migration: `colaborador_obras` (e carga das linhas atuais com 100%), `fechamento_rateios`. Models, factory, morph map.
2. `DefinirRateioObras`, `RateioDoFechamento` e gravação em `CalcularFechamentoColaborador`; escopo `Colaborador::naObra`.
3. Tela: bloco "Obras e rateio" no colaborador (obras, percentuais, a partir de), com soma em tempo real e histórico.
4. Relatórios, painel, calendário de feriados e filtros de obra.
5. Testes: soma 100, vigência, mudança no meio do mês, centavos, aprovação congela, relatório, filtro, migração dos dados atuais.
6. Painel, especificação (modelo 3.2 e 3.4) e manuais do RH e do financeiro.

## Situação (18/11/2026)

Feita e testada: 18 testes novos em `RateioObrasTest` (cadastro, validações, vigência, obra única sincronizada com o cadastro, divisão em centavos, mudança no meio do mês, aprovação congela, relatório, painel, conferência, tela e permissão) e a suíte completa verde. Validada no navegador (rateio 60/40 na ficha, erro de soma, ficha com as duas obras) e a migração rodou sobre os dados do demo (12 linhas de obra e 11 rateios de fechamento criados).

- **Regra acrescentada:** as obras do rateio precisam ser da empresa do colaborador (mesma regra do cadastro de obra única). Se a ABL PRIME ratear entre empresas, a regra sai de `DefinirRateioObras` num ponto só.
- **Obra principal:** só muda quando o rateio novo já vale hoje; rateio futuro não troca a principal. O escopo `naObra` não depende dela: usa o rateio vigente e, sem rateio, a obra do cadastro.
- **Conferência do fechamento** mostra o rateio por obra quando há mais de uma. O relatório de benefícios, com filtro de obra, soma só a parte do rateio.
- **Migração:** `2026_11_25_100001_create_rateio_por_obra`.
- **Pendente (ABL PRIME, P25 e P27):** se o rateio é por percentual fixo, dias ou horas, sobre quais valores, quem define os percentuais, e como o título vai ao UAU (um por obra ou com apropriação).
