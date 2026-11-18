# E6 · Fatia 1 — Painel com indicadores · 14 h

Detalha a fatia 1 do [plano da Entrega 6](entrega-6-relatorios-implantacao.md). Estende o painel que já existe (documentos, qualificações, colaboradores por cargo) em vez de reescrevê-lo.

## O que o painel passa a mostrar (cada bloco só para quem tem a permissão da tela de origem)

| Bloco | Permissão | Detalhe |
| --- | --- | --- |
| Admissões e desligamentos do mês | `colaborador-list` | respeita a visibilidade do gestor |
| Ativos sem equipe vigente | `colaborador-list` | o nível "gestor da equipe" das férias é pulado para eles |
| Ativos por obra | `colaborador-list` | |
| Custo do mês e gráfico dos últimos 12 meses | `fechamento-list` | só competências aprovadas (snapshot), Chart.js |
| Notas pendentes | `nota-list` | fechamentos aprovados sem nota válida (nunca enviada ou recusada) |
| Integrações com erro | `integracao-list` | falhas sem solução |
| Contratos vencendo em 30 dias | `contrato-list` | usa `ContratosAVencer` |
| Férias perto do limite | `ferias-list` | usa `FeriasAVencer` e o prazo do alerta |

## Decisões

- **D1.1 — Cache curto de 5 minutos por usuário** (`PAINEL_CACHE`, em segundos; 0 desliga, usado nos testes). O gestor tem chave própria porque o conjunto dele é diferente.
- **D1.2 — O custo lê só o que foi aprovado.** Competência em conferência não entra: o número do painel não muda de um dia para o outro por recálculo.
- **D1.3 — Gráfico com Chart.js via CDN de versão fixa;** sem JavaScript o quadro mostra a mesma série em tabela.
