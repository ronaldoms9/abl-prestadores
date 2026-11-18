# E6 · Fatias 2 e 3 — Relatórios e exportação · 32 h

Detalha as fatias 2 e 3 do [plano da Entrega 6](entrega-6-relatorios-implantacao.md).

## Relatórios

Menu Relatórios, um item por relatório, visível a quem tem a permissão da tela de origem (não há permissão nova): custo e benefícios (`fechamento-list`), férias (`ferias-list`), qualificações (`qualificacao-list`), contratos (`contrato-list`), notas (`nota-list`). O gestor vê só os colaboradores das equipes que gere.

| Relatório | Filtros | Linhas |
| --- | --- | --- |
| Custo por obra | competência de–até, empresa, obra | uma por obra: colaboradores, remuneração, benefícios, ajustes, líquido |
| Benefícios | competência de–até, benefício, obra | um por benefício: colaboradores, valor total |
| Férias e saldos | obra, equipe, saldo mínimo | um por colaborador ativo: saldo, limite de gozo mais próximo, dias aprovados a gozar |
| Qualificações vencendo | janela de dias, cargo, obra | uma por qualificação obrigatória ausente, vencida ou a vencer |
| Contratos vencendo | janela de dias, obra | um por contrato vencido ou que vence na janela |
| Notas pendentes | competência, situação, obra | um por fechamento aprovado, com a situação da nota |

- Os relatórios de custo leem só competências aprovadas (ou fechadas) e os totais gravados no fechamento, que não mudam depois da aprovação. A obra é a atual do colaborador (o snapshot não guarda a obra); se a ABL PRIME precisar da obra "da época", o snapshot passa a guardá-la.
- Cada relatório devolve colunas, linhas e uma linha de total; a tela, o Excel e o PDF usam o mesmo resultado, por isso têm as mesmas linhas.

## Exportação

- **Excel** (maatwebsite/excel): `RelatorioExport`, com cabeçalho, linhas e total.
- **PDF** (mesmo `GeradorPdf` dos contratos, dompdf quando instalado): cabeçalho com logo, filtros aplicados e data de emissão. Sem a biblioteca, a tela avisa em vez de quebrar.
- Limite de 5.000 linhas por exportação síncrona; acima disso a tela pede para refinar os filtros. O envio por job com link por e-mail fica para quando o volume real pedir (a ABL PRIME tem dezenas de prestadores).
