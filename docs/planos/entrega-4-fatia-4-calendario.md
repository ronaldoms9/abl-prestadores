# E4 · Fatia 4 — Calendário de ausências · 10 h

Detalha a fatia 4 do [plano da Entrega 4](entrega-4-ferias-fechamento.md).

## Escopo

- Tela Férias e ausências › Calendário (`ferias-list`): visão mensal com FullCalendar 6 (jsDelivr, versão fixa), em português.
- Mostra **férias aprovadas** (cheio), **pendentes** (tracejado), **faltas e afastamentos** e **feriados** (nacionais sempre; estaduais e municipais da obra filtrada, ou todos quando não há filtro de obra).
- Filtros por **obra** (obra do colaborador) e **equipe** (quem tem vínculo vigente em algum dia do período exibido).
- O gestor vê só os colaboradores das equipes que gere hoje (`Colaborador::visiveisPara`); o evento não traz dado sensível além do nome e do tipo.
- Os eventos vêm de um endpoint JSON (`start`/`end` do FullCalendar), por intervalo, sem carregar o ano inteiro.

## Decisões

- **D4.1 — Datas inclusive no banco, exclusivas no calendário.** O endpoint soma um dia ao fim, como o FullCalendar espera.
- **D4.2 — Cancelada e recusada não aparecem.**
- **D4.3 — Sem edição pelo calendário.** Clicar num evento não abre formulário; a edição continua nas telas de férias e ocorrências.
