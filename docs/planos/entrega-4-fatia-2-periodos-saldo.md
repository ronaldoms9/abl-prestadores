# E4 · Fatia 2 — Períodos aquisitivos e saldo · 10 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 2 do [plano da Entrega 4](entrega-4-ferias-fechamento.md).

## O que foi feito

- **Geração** (`GerarPeriodosAquisitivos`): períodos de 12 meses contados da admissão, colados, sem folga nem sobreposição. O corrente existe desde a admissão; o seguinte abre no dia em que o anterior completa. Admissão antiga gera todos os que já começaram. Só colaborador ativo com data de admissão. Pode rodar de novo: cria só o que falta.
- **Comando diário** `ferias:periodos`, às 05:30, para todo colaborador ativo. A conclusão da admissão (E3) também abre o primeiro período.
- **Direito e limite de gozo:** cada período guarda os dias de direito da configuração na criação (padrão 30) e o limite de gozo, que é o fim do período mais 12 meses. Em `configuracoes`: `ferias.dias_direito` e `ferias.limite_gozo_meses`; valor inválido cai no padrão.
- **Saldo** = direito − utilizados, nunca negativo. `AjustarSaldoFerias` é o único ponto que mexe em `dias_utilizados`: baixar recusa mais dias do que o saldo; devolver recusa mais do que os utilizados; o período é travado na transação. A aprovação e o cancelamento de férias (fatia 3) usam esta ação.
- **Alerta diário** `alertas:ferias`, às 07:15: e-mail-resumo para quem tem `alerta-ferias`, com os saldos cujo limite de gozo já passou ou chega em até 60 dias (`configuracoes[alertas.ferias].dias`). Mesmo formato e mesma regra de "sem destinatário" dos demais alertas.
- **Ficha do colaborador:** seção "Férias" com os períodos, o saldo de cada um, o saldo total e a marca de limite vencido, para quem tem `ferias-list`.

## Decisões

- **D2.1 — O último dia do período é a véspera do aniversário da admissão;** admissão em 29/02 usa 28/02 nos anos comuns, sem estourar o mês.
- **D2.2 — O limite de gozo é inclusivo** (último dia para gozar), como a validade dos documentos.
- **D2.3 — O direito gravado não muda depois.** Mudar `ferias.dias_direito` vale só para os períodos novos.

## Pendente (pergunta à ABL PRIME, spec 4.4)

- Dias de direito, prazo de gozo e fracionamento mínimo das férias do PJ. Os padrões acima (30 dias, 12 meses) vêm do plano e são configuráveis.
