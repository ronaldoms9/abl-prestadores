# E6 · Fatias 4 e 6 — Qualidade e manuais (o que não depende do UAU, da ABL PRIME nem de servidor)

Detalha partes das fatias 4 e 6 do [plano da Entrega 6](entrega-6-relatorios-implantacao.md).

## Fatia 4 — testes finais e revisão

- **Matriz de permissões** (`MatrizDePermissoesTest`): percorre todas as rotas registradas. Exige que toda rota autenticada tenha permissão (ou esteja numa lista curta de exceções justificadas), que quem tem todas as permissões do sistema menos as da rota receba 403 e que quem tem a permissão não seja barrado por ela. Rota nova sem proteção quebra o teste sem ninguém lembrar de listá-la. Também confere a matriz de acesso dos perfis (gestor sem remuneração, contrato, fechamento e nota; só o financeiro aprova; só o administrador reabre; colaborador só com o portal).
- **Desempenho** (`DesempenhoConsultasTest`): cada tela que cresce com o número de colaboradores é medida com 5 e com 20 e o número de consultas não pode crescer. A medição achou um N+1 real na fila de aprovação de férias (cerca de 4 consultas por solicitação); `AprovadoresFerias::carregarLote` carrega níveis, permissões e gestores de uma vez. Índices novos em `colaboradores` (admissão e desligamento) para o fechamento, o painel e os relatórios.
- **Exportação grande em job:** acima de 1.000 linhas (`cadastros.relatorios.limite_sincrono`) a exportação roda em job e chega por e-mail com link assinado de 24 horas, só de quem pediu; arquivos com mais de dois dias são apagados.
- **Ciclo completo** (`CicloCompletoTest`): admissão, contrato, benefício, férias em dois níveis, fechamento do mês, aprovação, bloqueio retroativo, nota enviada pelo portal, aprovação da nota, relatórios e painel, tudo sem o UAU.

## Fatia 6 — manuais

Manuais por perfil (RH, gestor, financeiro, colaborador) e o guia de operação em `docs/manual/*.md`, mostrados no sistema em **Ajuda**, cada um só para quem tem a permissão do perfil. Um teste garante que os nomes de menu citados existem. As sessões gravadas de treinamento ficam com a equipe e a ABL PRIME.

## Fica de fora

Produção (servidor, domínio, HTTPS, backups, Sentry), o ciclo com o UAU de homologação, o título no UAU e as correções que a homologação apontar.
