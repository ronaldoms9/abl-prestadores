# Entrega 4 — Férias e fechamento · 104 h · semanas 10–12

Ao fim da E4, cada colaborador tem períodos aquisitivos e saldo de férias, as
solicitações passam por aprovação em níveis com e-mail, e o RH fecha o mês: o
sistema calcula remuneração e benefícios proporcionais, o RH ajusta, o
financeiro aprova e o fechamento fica imutável.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| Proporcionalidade por dias corridos ou úteis (spec 4.3) | ABL PRIME | Fatia 6 |
| Sábado é dia trabalhado? Por colaborador ou por obra? (spec 4.2) | ABL PRIME | Fatia 6 |
| Férias do PJ: dias de direito, fracionamento mínimo, níveis de aprovação (spec 4.4) | ABL PRIME | Fatias 2–3 |
| Férias reduzem a remuneração? (spec 4.3) | ABL PRIME | Fatia 6 |
| Feriados estaduais e municipais das obras | ABL PRIME | Fatia 5 |

A `CalculadoraFechamento` recebe essas regras por configuração; se alguma
chegar tarde, o padrão é: dias corridos, sábado não trabalhado, férias não
reduzem a remuneração, benefício fixo proporcional.

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Migrations e models da spec 3.4 | 6 |
| 2 | Períodos aquisitivos e saldo | 10 |
| 3 | Solicitação e aprovação em níveis | 18 |
| 4 | Calendário de ausências | 10 |
| 5 | Feriados e ocorrências | 8 |
| 6 | CalendarioTrabalho e CalculadoraFechamento | 26 |
| 7 | Competência, conferência, ajustes e aprovação | 20 |
| 8 | Homologação | 6 |
| | **Total** | **104** |

### 1. Migrations e models — 6 h

Tabelas da spec 3.4: `ferias_periodos`, `ferias_solicitacoes`, `ferias_aprovacoes`, `feriados`, `ocorrencias`, `competencias`, `fechamentos`, `fechamento_itens`, `ajustes_avulsos`. Acréscimos:
- `ferias_niveis` (ordem, tipo_aprovador `gestor_equipe` | `perfil`, role_id nullable, ativo). Seed padrão: 1. gestor da equipe → 2. RH.
- `ferias_solicitacoes.nivel_atual`.
- `ferias_aprovacoes.escalada` (bool) e `motivo_escalada` — registra quando um nível foi pulado ou subiu por falta de aprovador.

### 2. Períodos aquisitivos e saldo — 10 h

- Ação `GerarPeriodosAquisitivos`: 12 meses a partir da admissão, `dias_direito` da configuração (padrão 30), `limite_gozo` configurável.
- Comando diário abre o próximo período quando o atual completa 12 meses; roda também ao concluir a admissão (E3).
- Saldo = direito − utilizados; nunca negativo.
- Alerta por e-mail quando o saldo está a N dias do limite de gozo.

### 3. Solicitação e aprovação em níveis — 18 h

- Solicitação por RH ou gestor (o colaborador solicita pelo portal a partir da E5); valida saldo, fracionamento mínimo, sobreposição com outra solicitação e desligamento. Gestor só solicita para colaboradores que gere.
- Aviso (não bloqueio) de conflito: outro colaborador de uma mesma equipe ou da mesma obra ausente no período.
- Fluxo: cada nível aprova ou recusa com observação; recusa encerra; o último nível aprova a solicitação, baixa o saldo e grava `dias_utilizados`.
- Serviço `AprovadoresFerias::doNivel($solicitacao, $nivel, $data)` (regras do README, D7):
    - nível `gestor_equipe`: gestores vigentes em `$data` de todas as equipes vigentes em `$data` do colaborador;
    - nível `perfil`: usuários ativos do perfil;
    - remove o solicitante e o próprio colaborador (se tiver usuário);
    - lista vazia → o nível é pulado e registrado como escalada; sem nível seguinte → admin.
- Aprovadores calculados no dia da decisão: troca de equipe com solicitação pendente muda a fila automaticamente; os novos gestores recebem e-mail.
- Qualquer aprovador do nível decide; os demais deixam de ver a pendência.
- E-mails: nova solicitação a todos os aprovadores do nível; decisão ao solicitante.
- Cancelamento de férias aprovadas devolve o saldo, se a competência não estiver fechada.
- Tela de configuração dos níveis (admin): ordem, tipo, perfil.

### 4. Calendário de ausências — 10 h

- Visão mensal com FullCalendar (via CDN), filtros por obra (obra do colaborador) e por equipe (membros com vínculo vigente em algum dia do período exibido).
- Mostra férias aprovadas, pendentes (tracejado) e ocorrências.
- Gestor vê só os colaboradores das equipes que gere hoje.

### 5. Feriados e ocorrências — 8 h

- CRUD de feriados (nacional, estadual, municipal por obra); importação dos nacionais do ano (BrasilAPI).
- CRUD de ocorrências: falta, afastamento, com período e "abona".
- Ocorrência em competência fechada é recusada.

### 6. CalendarioTrabalho e CalculadoraFechamento — 26 h

`app/Services/Fechamento/`.

- `CalendarioTrabalho::diasTrabalhados($colaborador, $inicio, $fim)`: dias úteis − feriados (da obra) − férias − faltas não abonadas, com a regra do sábado.
- `CalculadoraFechamento::calcular($colaborador, $competencia)`: devolve os itens, sem gravar.
    - Remuneração: chama `RemuneracaoProporcional` (E3) dividindo o mês nos trechos de vigência.
    - Benefício fixo: valor vigente, proporcional ao período ativo conforme a regra.
    - Benefício por dia: valor vigente do dia × dias trabalhados, também por trecho de vigência.
    - Ajustes avulsos do mês: crédito soma, débito subtrai.
- Cada item guarda descrição, quantidade, valor unitário e origem (morph) → memória de cálculo.
- Valores em centavos (inteiro) ou `bcmath`; nunca float. Arredondamento só no total de cada item.

**Matriz de testes** (um teste por linha, valores esperados escritos à mão em planilha validada com o RH):

| Cenário | Remuneração | Fixo | Por dia |
| --- | --- | --- | --- |
| Mês cheio | ✓ | ✓ | ✓ |
| Admissão dia 15 | ✓ | ✓ | ✓ |
| Desligamento dia 10 | ✓ | ✓ | ✓ |
| Reajuste dia 16 | ✓ | — | — |
| Reajuste de benefício dia 16 | — | ✓ | ✓ |
| Férias de 10 dias | ✓ | ✓ | ✓ |
| Feriado municipal da obra | — | — | ✓ |
| Falta abonada e não abonada | — | — | ✓ |
| Fevereiro (28 e 29 dias) | ✓ | ✓ | ✓ |
| Admissão e desligamento no mesmo mês | ✓ | ✓ | ✓ |

### 7. Competência, conferência, ajustes e aprovação — 20 h

- Abrir competência (`aberta`): calcula todos os colaboradores ativos em algum dia do mês (job em lote).
- Conferência (`em_conferencia`): tabela por colaborador com totais; detalhe com a memória de cálculo; RH lança ajustes avulsos com justificativa e recalcula (um ou todos).
- Aprovação (financeiro): grava `snapshot` json com os valores usados, muda para `aprovada`, bloqueia recálculo e lançamentos com data no mês (remuneração, benefício, ocorrência, férias).
- Fechamento aprovado fica "aguardando nota" (status usado na E5).
- Ligar aqui as checagens de "competência fechada" deixadas prontas na E2 e E3.
- Auditoria de abertura, recálculo, ajuste e aprovação.
- Reabrir competência aprovada: só admin, com justificativa, e só se nenhum título foi enviado ao UAU.

### 8. Homologação — 6 h

Fechamento paralelo de um mês real: a ABL PRIME compara o resultado com o cálculo atual dela.

## Testes obrigatórios

- Matriz de cenários da fatia 6.
- Saldo de férias: geração do período, baixa na aprovação, devolução no cancelamento, recusa por saldo insuficiente.
- Aprovação em dois níveis; gestor não aprova fora das suas equipes.
- `AprovadoresFerias`: colaborador em duas equipes (gestores das duas elegíveis); colaborador sem equipe (nível pulado); gestor pedindo as próprias férias com e sem outro gestor na equipe; RH pedindo as próprias férias no último nível (vai ao admin); troca de equipe com solicitação pendente; gestor com vínculo encerrado deixa de ser elegível.
- Fechamento aprovado não recalcula e recusa novos lançamentos no mês.

## Critério de aceite

1. Fechamento paralelo de um mês bate com o cálculo da ABL PRIME, centavo a centavo, ou a diferença é explicada por regra.
2. Uma solicitação feita pelo RH chega por e-mail aos gestores das equipes do colaborador, passa pelos dois níveis e aparece no calendário e no saldo. (Solicitação pelo próprio colaborador só na E5 — avisar no roteiro.)
3. Um gestor que pede as próprias férias não as aprova: a solicitação vai a outro gestor da equipe ou ao RH.
4. Após a aprovação do fechamento, nenhum valor do mês muda.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| Regras de proporcionalidade definidas tarde | Retrabalho no cálculo | Regras em configuração desde o início; testes parametrizados |
| Divergência no fechamento paralelo | Aceite atrasa | Memória de cálculo linha a linha para achar a regra que diverge |
| Regra de férias do PJ difere por contrato | Configuração global não basta | `dias_direito` por colaborador, com padrão global |
