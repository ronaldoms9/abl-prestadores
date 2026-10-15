# Entrega 3 — Contratos e benefícios · 112 h · semanas 7–9

Ao fim da E3, o RH gera contrato, aditivo e distrato em PDF a partir do
cadastro, admite colaboradores por checklist, desliga com distrato e bloqueio,
e mantém a tabela de benefícios com vigência ligada a cada colaborador.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| Modelos atuais de contrato, aditivo e distrato (Word/PDF) | ABL PRIME (jurídico) | Fatias 2–3 |
| Itens do checklist de admissão | ABL PRIME (RH) | Fatia 5 |
| Benefício fixo é proporcional na admissão e no desligamento? (spec 4.2) | ABL PRIME | Fatia 7 |
| Base da proporcionalidade: dias corridos ou úteis? (spec 4.3) | ABL PRIME | Fatia 6 |
| Lista de benefícios e valores atuais | ABL PRIME (RH) | Fatia 7 |

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Migrations e models da spec 3.3 | 8 |
| 2 | Modelos de contrato e variáveis | 16 |
| 3 | Geração de contrato, aditivo e distrato em PDF | 20 |
| 4 | Vigência e alertas de contrato | 6 |
| 5 | Admissão com checklist | 14 |
| 6 | Desligamento | 14 |
| 7 | Benefícios com vigência e reajuste em lote | 14 |
| 8 | Benefícios do colaborador | 8 |
| 9 | Reajuste gera aditivo | 6 |
| 10 | Testes e homologação | 6 |
| | **Total** | **112** |

### 1. Migrations e models — 8 h

Tabelas da spec 3.3: `contrato_modelos`, `contratos`, `beneficios`, `beneficio_valores`, `colaborador_beneficios`, `admissao_checklist`. Acréscimo: `admissao_checklist_itens` (itens configuráveis, ordem, obrigatório) — o `admissao_checklist` da spec guarda o estado por colaborador.

### 2. Modelos de contrato e variáveis — 16 h

- Editor de `corpo_html` com editor rico simples (TinyMCE ou Trix) e painel lateral com as variáveis clicáveis.
- Catálogo de variáveis em código (`ContratoVariaveis`): `{{colaborador.nome}}`, `{{pj.cnpj}}`, `{{pj.razao_social}}`, `{{cargo.nome}}`, `{{remuneracao.valor}}`, `{{remuneracao.valor_extenso}}`, `{{contrato.vigencia_inicio}}`, `{{beneficios.lista}}` etc.
- Renderizador próprio (substituição de `{{…}}`), nunca Blade com conteúdo do usuário. Variável desconhecida bloqueia o salvamento do modelo.
- Versionamento: salvar um modelo em uso cria nova `versao`; contratos antigos apontam para a versão usada.
- Formatação BR: moeda, data por extenso, valor por extenso (biblioteca ou helper próprio).

### 3. Geração em PDF — 20 h

- Ação `GerarContrato` (tipos contrato, aditivo, distrato): renderiza, grava `conteudo_renderizado`, gera PDF com dompdf em job, status `gerado`.
- Numeração `AAAA/NNNN`; aditivos `AAAA/NNNN-A01` e distrato `AAAA/NNNN-D`, apontando para `contrato_pai_id`. Prefixo e reinício anual configuráveis em `configuracoes` (padrão: sem prefixo, reinicia todo ano).
- Pré-visualização HTML antes de gerar.
- Upload do PDF assinado → status `assinado`.
- Linha do tempo na tela do colaborador: contrato, aditivos e distrato, com download de cada versão.
- Contrato gerado é imutável; correção = novo aditivo ou cancelar o rascunho.

### 4. Vigência e alertas — 6 h

- Comando diário `alertas:contratos` para quem tem `alerta-contratos`: vencendo em 30/15/5 dias e vencidos.
- Ação "Renovar": gera aditivo de prorrogação com nova `vigencia_fim`.

### 5. Admissão com checklist — 14 h

- Ação `IniciarAdmissao`: colaborador em `pre_admissao` recebe os itens configurados.
- Itens automáticos marcados pelo sistema: "PJ gravada no UAU", "contrato assinado anexado", "qualificações obrigatórias do cargo em dia", "documentos obrigatórios enviados". Equipe não é item obrigatório (README, D7).
- Itens manuais marcados pelo RH, com autor e data.
- `ConcluirAdmissao`: exige todos os obrigatórios, muda o status para `ativo`, grava `data_admissao`, cria a primeira remuneração e os benefícios padrão do cargo.

### 6. Desligamento — 14 h

- Ação `DesligarColaborador`: data, motivo, observação; gera o distrato pelo modelo.
- Serviço `RemuneracaoProporcional` (data inicial, data final, remuneração vigente em cada dia → valor) com a base de dias definida no kickoff. É o mesmo cálculo que a `CalculadoraFechamento` (E4) vai chamar.
- Prévia dos valores finais na tela de desligamento: remuneração proporcional + benefícios fixos conforme a regra. **Limite decidido:** até a E4, a prévia não inclui benefícios por dia, faltas nem feriados; eles entram no fechamento do mês. A tela mostra esse aviso, e ele consta do roteiro de homologação da E3.
- Bloqueio: colaborador `desligado` não recebe remuneração, benefício, ajuste, férias ou nota com data posterior ao desligamento (Policy + validação nas ações).
- Encerra contrato (`encerrado`), remuneração e benefícios abertos na data do desligamento.

### 7. Benefícios com vigência — 14 h

- CRUD de benefícios (tipo de cálculo: `fixo_mensal` ou `por_dia_trabalhado`).
- Tabela de valores com vigência; reajuste encerra a linha e cria outra (mesma regra da remuneração).
- Reajuste em lote de benefícios com prévia, como na E2.
- Mudança de valor com vigência em mês já fechado é recusada (liga na E4).

### 8. Benefícios do colaborador — 8 h

- Vincular benefício com início, fim e `valor_personalizado` opcional.
- Valor efetivo em uma data = personalizado, senão o da tabela vigente (`BeneficioVigente::valorEm($colaborador, $beneficio, $data)`).
- Benefícios padrão por cargo, aplicados na admissão.

### 9. Reajuste gera aditivo — 6 h

- Listener de `ReajusteAplicado` (E2) gera aditivo em rascunho para cada colaborador reajustado, com o modelo de aditivo de reajuste.
- Lote: aditivos gerados em job; tela lista os rascunhos para gerar PDF em massa.
- Mesma regra para mudança de cargo e alteração de benefícios.

### 10. Testes e homologação — 6 h

Deploy, cadastro dos modelos reais da ABL PRIME, roteiro de validação.

## Testes obrigatórios

- Renderizador: todas as variáveis, variável desconhecida recusada, HTML do usuário não executa código.
- Numeração de contrato e aditivo sem colisão em gerações simultâneas (lock).
- `RemuneracaoProporcional`: mês cheio, admissão no meio, desligamento no meio, reajuste no meio, fevereiro.
- Valor efetivo do benefício: personalizado, tabela vigente, troca de vigência no meio do mês.
- Bloqueio de lançamentos após o desligamento.

## Critério de aceite

1. Contrato gerado a partir do cadastro sai idêntico ao modelo da ABL PRIME, com os dados preenchidos.
2. Reajuste em lote de um cargo gera um aditivo por colaborador.
3. Admissão só conclui com todos os itens obrigatórios; o colaborador vira `ativo`.
4. Desligamento gera distrato, mostra a prévia dos valores finais (remuneração + benefício fixo) e bloqueia novos lançamentos.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| Modelos com formatação complexa (tabelas, cabeçalhos) | dompdf não reproduz fielmente | Ajustar o HTML do modelo; se não bastar, trocar por Browsershot (Chromium) — +6 h |
| Modelos chegam tarde | Fatias 2–3 sem conteúdo real | Desenvolver com um modelo de exemplo; os reais entram na homologação |
