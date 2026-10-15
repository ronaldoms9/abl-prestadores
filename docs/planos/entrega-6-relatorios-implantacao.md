# Entrega 6 — Relatórios e implantação · 80 h · semanas 16–17

Ao fim da E6, o sistema está em produção ligado ao UAU de produção, com painel,
os seis relatórios da proposta exportáveis em Excel/PDF, backups e
monitoramento de erros, e a equipe da ABL PRIME treinada. Começa a garantia de
90 dias.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| Servidor de produção contratado (opção econômica ou recomendada, proposta seção 6) | ABL PRIME | Fatia 5 |
| Domínio e DNS | ABL PRIME | Fatia 5 |
| Usuário técnico e URL HTTPS da UAU API de produção | ABL PRIME | Fatia 5 |
| Serviço de e-mail transacional | ABL PRIME / nós | Fatia 5 |
| Lista de usuários reais e perfis | ABL PRIME | Fatias 5–6 |
| Data da virada e primeira competência a fechar no sistema | ABL PRIME | Fatia 5 |

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Painel com indicadores | 14 |
| 2 | Relatórios | 24 |
| 3 | Exportação Excel/PDF | 8 |
| 4 | Testes finais e ajustes da homologação | 12 |
| 5 | Produção | 14 |
| 6 | Treinamento e manual | 8 |
| | **Total** | **80** |

### 1. Painel — 14 h

Página inicial por perfil, substituindo o `HomeController` atual.

- Indicadores: colaboradores ativos, admissões e desligamentos do mês, custo do mês (último fechamento), notas pendentes, integrações com erro.
- Gráfico do custo mensal dos últimos 12 meses (Chart.js) e quadro de ativos por obra.
- Listas de atenção: documentos, qualificações e contratos vencendo em 30 dias; férias perto do limite; colaboradores ativos sem equipe vigente (o nível "gestor da equipe" é pulado para eles).
- Gestor vê só os colaboradores das equipes que gere hoje; financeiro vê custo e notas; RH vê cadastros e vencimentos.
- Consultas com cache curto (5 min) para não pesar.

### 2. Relatórios — 24 h

Seis relatórios da proposta (seção 2.11), cada um com filtros e tabela:

| Relatório | Filtros | Fonte |
| --- | --- | --- |
| Custo por obra / centro de custo | Competência (de–até), empresa, obra | `fechamentos` + `fechamento_itens` (snapshot) |
| Benefícios | Competência, benefício, obra | `fechamento_itens` tipo benefício |
| Férias e saldos | Obra, equipe, saldo mínimo | `ferias_periodos`, `ferias_solicitacoes` |
| Qualificações vencendo | Janela de dias, cargo, obra | `colaborador_qualificacoes` + matriz |
| Contratos vencendo | Janela de dias, obra | `contratos` |
| Notas pendentes | Competência, status, obra | `notas_fiscais` + `fechamentos` |

Relatórios de custo leem só fechamentos aprovados (snapshot), nunca recalculam.

### 3. Exportação — 8 h

- Excel com maatwebsite/excel (uma classe `Export` por relatório, com os mesmos filtros da tela).
- PDF com dompdf, cabeçalho com logo, filtros aplicados e data de emissão.
- Exportações grandes em job, com link na tela quando prontas.

### 4. Testes finais e ajustes — 12 h

- Ciclo completo em homologação: admissão → contrato → férias → fechamento → nota → título.
- Correção dos apontamentos das homologações anteriores ainda abertos.
- Revisão de permissões rota a rota contra a matriz validada.
- Revisão de desempenho: índices nas consultas dos relatórios, N+1 nas listas.

### 5. Produção — 14 h

- Provisionar servidor (PHP 8.4, MySQL 8, Redis, Nginx/Apache, Supervisor para o Horizon, cron do scheduler), HTTPS com Let's Encrypt.
- Deploy de `main` com o mesmo script da homologação; `.env` de produção com as credenciais do UAU de produção.
- Backup diário do banco e de `storage/app` fora do servidor, com teste de restauração documentado.
- Sentry para erros; alerta de fila parada (Horizon) e de falhas de integração por e-mail.
- Virada: importação completa do UAU de produção, conferência de amostra com a ABL PRIME, criação dos usuários reais, cadastro de modelos, benefícios e de-para de produção.
- Migração de dados legados não está no escopo (proposta seção 8): cadastros complementares são digitados pela ABL PRIME ou orçados à parte.

### 6. Treinamento e manual — 8 h

- Manual de uso por perfil (RH, gestor, financeiro, colaborador), com telas, em Markdown publicado como página no próprio sistema ou PDF.
- Duas sessões online gravadas: equipe interna (RH/financeiro/gestores) e um roteiro curto para os colaboradores (envio de nota e férias).
- Guia de operação para o suporte: deploy, reprocessar integração, reabrir competência, restaurar backup.

## Testes obrigatórios

- Cada relatório: filtros e totais conferidos contra os dados de teste.
- Exportações geram arquivo com as mesmas linhas da tela.
- Teste de fumaça em produção após o deploy (login, painel, importação UAU, um envio de teste no UAU se a ABL PRIME autorizar).

## Critério de aceite

1. Relatório de custo por obra de um mês fechado bate com a soma dos fechamentos aprovados.
2. Sistema em produção, ligado ao UAU de produção, com backup diário verificado.
3. Equipe treinada; primeira competência real aberta no sistema.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| UAU de produção liberado tarde | Virada atrasa | Produção sobe ligada à homologação do UAU; troca de credenciais na liberação |
| Apontamentos acumulados das entregas anteriores | Fatia 4 estoura | Apontamentos fora do escopo vão para o pacote de horas pós-garantia |
