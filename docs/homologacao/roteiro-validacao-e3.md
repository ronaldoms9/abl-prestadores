# Roteiro de validação da Entrega 3 — Contratos e benefícios (rascunho)

Rascunho para a equipe da ABL PRIME conferir a Entrega 3 em homologação. Segue o formato do
[roteiro da Entrega 1](roteiro-validacao.md). Os critérios são os do
[plano da entrega](../planos/entrega-3-contratos-beneficios.md); o 1 só fecha com os modelos
reais do jurídico cadastrados (fatia 10).

**Antes de começar:** o jurídico entrega os modelos de contrato, aditivo e distrato; o RH entrega
a lista de benefícios com valores e os itens do checklist de admissão; a ABL PRIME decide a
proporcionalidade (dias corridos ou úteis; benefício fixo proporcional ou cheio).

---

## Critério 1 — O contrato sai igual ao modelo da ABL PRIME, com os dados preenchidos

**Quem faz:** RH.

1. Em Contratos › Modelos, cadastre os modelos reais de contrato, aditivo e distrato, usando as
   variáveis do painel lateral.
2. Na ficha de um colaborador, crie o contrato, confira a pré-visualização e gere.
3. Baixe o PDF e compare com o modelo original.
4. Anexe o arquivo assinado.

**Esperado:**
- o texto e a formatação batem com o modelo; os dados do cadastro entram nos lugares certos;
- se faltar dado no cadastro, o sistema diz qual e não gera;
- o número sai no formato `AAAA/NNNN`; depois de gerado o contrato não muda.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 2 — Reajuste em lote gera um aditivo por colaborador

**Quem faz:** RH.

1. Em Cargos e salários › Reajuste em lote, reajuste um cargo e confirme.
2. Em Contratos › Aditivos pendentes, confira os rascunhos, selecione e gere.

**Esperado:** um rascunho de aditivo por colaborador do cargo que tem contrato em vigor, com o
valor antigo e o novo; ao gerar, cada aditivo ganha número `AAAA/NNNN-A01` e PDF.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 3 — A admissão só conclui com todos os itens obrigatórios

**Quem faz:** RH.

1. Na ficha de um colaborador em pré-admissão, inicie a admissão.
2. Tente concluir com itens pendentes.
3. Resolva os itens (marque os manuais, anexe documentos, assine o contrato) e conclua.

**Esperado:** a conclusão lista o que falta; com tudo em dia o colaborador vira "Ativo", com a
data de admissão e os benefícios padrão do cargo.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 4 — O desligamento gera o distrato, mostra a prévia e bloqueia novos lançamentos

**Quem faz:** RH.

1. Na ficha de um colaborador ativo, clique em "Desligar colaborador", informe o último dia de
   trabalho e o motivo, e confira a prévia dos valores finais.
2. Confirme, mantendo "Gerar o distrato".
3. Tente reajustar a remuneração ou dar um benefício ao colaborador desligado.

**Esperado:**
- o distrato sai com o número `AAAA/NNNN-D`; o contrato fica "Encerrado";
- a prévia mostra a remuneração proporcional e os benefícios fixos;
- remuneração e benefícios novos são recusados.

> **Aviso para a equipe:** até a Entrega 4, a prévia **não inclui benefícios por dia trabalhado,
> faltas nem feriados**. Eles entram no fechamento do mês. A tela mostra esse aviso.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Alertas de vencimento

O sistema envia, às 07:00, um resumo por e-mail aos usuários com a permissão "Receber alerta de
contratos": contratos que vencem em 30, 15 e 5 dias e os já vencidos (todo dia, até renovar).
Confira com um contrato de prazo curto. Para prorrogar, use "Renovar" na ficha do colaborador.

## O que não faz parte desta entrega

Folha de pagamento CLT, assinatura digital, férias e fechamento mensal (Entregas 4 e 5).

## Aprovação

| Nome | Cargo | Data | Assinatura |
| --- | --- | --- | --- |
| | | | |
