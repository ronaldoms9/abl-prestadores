# Manual do RH

Este manual cobre o dia a dia do RH no sistema: cadastrar, admitir, contratar, lançar férias e ocorrências e conferir o fechamento do mês. As telas estão no menu lateral; o que você vê depende do seu perfil.

## 1. Colaboradores

**Colaboradores** lista todos, com busca por nome, CPF ou CNPJ e filtro de situação (pré-admissão, ativo, desligado). Clique no olho para abrir a **ficha**: dados, remuneração, férias, contratos, benefícios, equipes, qualificações e documentos ficam numa tela só.

- **Novo colaborador:** escolha a pessoa física, a pessoa jurídica (o prestador), o cargo, a empresa contratante e a obra. Ele nasce em **pré-admissão**.
- Pessoas e empresas vêm do UAU: se alguém não aparece, a sincronização das 05:00 ainda não trouxe, ou use **Pessoas › Carregar planilha**.

## 2. Admitir um colaborador

Na ficha, em **Admissão**, clique em **Iniciar admissão**. O checklist mostra o que falta:

| Item | Como se resolve |
| --- | --- |
| Pessoa jurídica gravada no UAU | automático (hoje aguarda a gravação no UAU) |
| Remuneração definida | defina na seção Remuneração |
| Documentos obrigatórios enviados | envie na seção Documentos |
| Qualificações obrigatórias do cargo em dia | registre em Qualificações |
| Contrato assinado anexado | gere o contrato e anexe o arquivo assinado |
| Itens manuais | marque um a um (a lista é configurada em Admissão › Checklist) |

Com tudo em dia, informe a **data de admissão** e a **remuneração inicial** e clique em **Concluir admissão**. O colaborador vira **Ativo**, recebe os benefícios padrão do cargo e o primeiro período de férias.

## 3. Remuneração e benefícios

- **Remuneração:** só se cria linha nova; o reajuste encerra a anterior no dia anterior. Para vários colaboradores use **Cargos e salários › Reajuste em lote**: escolha o critério, a forma (percentual ou valor), veja a prévia e confirme.
- **Benefícios:** a **Tabela de benefícios** guarda o valor de cada um, com vigência. **Reajuste de benefícios** altera a tabela em lote. Na ficha você vincula ou encerra um benefício do colaborador (com valor personalizado, se for o caso).
- Mês já aprovado não aceita lançamento retroativo.

## 4. Contratos

Na ficha, em **Contratos**, escolha o modelo e as datas e clique em **Criar**: aparece uma pré-visualização com os dados preenchidos. Se faltar dado no cadastro, o sistema diz qual. **Gerar** numera o documento (`AAAA/NNNN`) e ele não muda mais. Depois, **Anexar assinado**.

- **Aditivos** são criados sozinhos como rascunho quando há reajuste, mudança de cargo ou alteração de benefícios. Confira e gere em **Contratos › Aditivos pendentes**.
- **Modelos** (Contratos › Modelos): o editor tem as variáveis no painel lateral; clique numa variável para inseri-la onde está o cursor. Alterar um modelo já usado cria uma nova versão.
- **Renovar** prorroga um contrato com data de fim; o sistema avisa por e-mail quando um contrato vence (30, 15 e 5 dias antes, e todo dia depois de vencido).
- O PDF depende da biblioteca de PDF estar instalada no servidor; se aparecer "PDF pendente", peça ao suporte.

## 5. Desligamento

Na ficha, **Desligar colaborador**: informe o **último dia de trabalho** e o motivo. A prévia mostra a remuneração proporcional e os benefícios fixos do mês (benefício por dia trabalhado, faltas e feriados entram no fechamento). Confirmar encerra contrato, remuneração, benefícios e equipes e gera o distrato.

## 6. Férias, feriados e faltas

- **Férias:** na ficha, seção Férias, escolha o período aquisitivo, as datas e clique em **Solicitar**. O pedido passa pelos níveis (gestor da equipe, depois RH) e o saldo só baixa na aprovação final. **Férias e ausências › Solicitações de férias** mostra o que espera a sua decisão.
- **Calendário:** férias aprovadas e pendentes, faltas, afastamentos e feriados, com filtro por obra e equipe.
- **Feriados:** nacionais podem ser importados do ano; estaduais e municipais são cadastrados por obra.
- **Ocorrências:** lance faltas e afastamentos. Marque **abonada** quando for justificada (não desconta o benefício por dia).

## 7. Fechamento do mês

Em **Fechamento mensal**, informe o mês e **Abrir competência**: o sistema calcula todos os que estiveram ativos. Depois:

1. Abra cada **Detalhe** e confira a memória de cálculo.
2. Lance **ajustes avulsos** (crédito ou débito, com justificativa); o colaborador é recalculado na hora.
3. Se mudou algo depois do cálculo (feriado, falta, férias), clique em **Recalcular** no colaborador ou **Recalcular todos**.
4. Avisos amarelos indicam remuneração ou valor de benefício faltando: corrija antes de pedir a aprovação.

A aprovação é do financeiro. Depois dela nada do mês muda.

## 8. Notas fiscais

Em **Notas fiscais**, escolha a competência. O colaborador envia a própria nota pelo portal; você pode **Enviar em nome dele** (PDF e, se houver, XML; sem XML, digite os campos). **Ver** mostra os dados lidos e as divergências.

## 9. Relatórios

**Relatórios** reúne custo por obra, benefícios, férias e saldos, qualificações vencendo, contratos vencendo e notas pendentes. Cada um tem filtros e exporta para Excel e PDF; relatórios grandes chegam por e-mail.

## 10. Dúvidas frequentes

- **"Competência já fechada":** o mês foi aprovado. Só o administrador reabre, com justificativa.
- **Não aparece alguém no fechamento:** ele precisa ter data de admissão e estar ativo em algum dia do mês.
- **O valor do benefício por dia parece baixo:** feriados da obra, férias e faltas não abonadas não contam como dia trabalhado.
