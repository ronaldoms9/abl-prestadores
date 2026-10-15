# Entrega 5 — Notas fiscais e contas a pagar · 88 h · semanas 13–15

Ao fim da E5, o colaborador envia a NFS-e do mês pelo portal, o sistema lê o
XML e confere com o fechamento, o financeiro aprova, e o título de contas a
pagar é lançado no UAU com a nota anexada. O pagamento continua sendo feito
pelo UAU.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| Municípios emissores das notas dos prestadores (spec 4.6) | ABL PRIME | Fatia 3 |
| Amostras reais de XML de cada município (2 a 3 por layout) | ABL PRIME | Fatia 3 |
| Endpoint de inclusão de título/processo de pagamento e de anexo/comentário | Nós (Swagger) + ABL PRIME | Fatia 6 |
| Regra de vencimento do título (ex.: dia 10 do mês seguinte) | ABL PRIME (financeiro) | Fatia 6 |
| De-para: tipo de conta, plano de contas, centro de custo por obra | ABL PRIME (financeiro) | Fatia 6 |
| Prazo de envio da nota (dia do mês) e tolerância de valor | ABL PRIME | Fatias 4–5 |

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Migrations e models da spec 3.5 | 4 |
| 2 | Portal: envio de nota, status e histórico | 14 |
| 3 | Leitor de XML | 16 |
| 4 | Conferência, aprovação e recusa | 14 |
| 5 | Alertas e download em lote | 8 |
| 6 | Título de contas a pagar no UAU | 20 |
| 7 | Testes e homologação | 12 |
| | **Total** | **88** |

### 1. Migrations e models — 4 h

Tabelas da spec 3.5: `notas_fiscais`, `nota_retencoes`, `titulos_pagar`. Unicidade: `chave_acesso` única; sem chave, único em (`cnpj_emissor`, `numero`, `serie`) — validado na ação, porque as colunas são nulas em parte dos casos.

### 2. Portal: envio de nota — 14 h

Sobre a casca do portal da E4.

- "Minhas notas": competências com fechamento aprovado e o valor esperado; status de cada nota.
- Envio: XML (obrigatório quando o município emite XML) e PDF; leitura imediata mostra os dados lidos para o colaborador conferir antes de confirmar.
- Nota recusada mostra o motivo e libera o reenvio.
- RH/financeiro enviam em nome do colaborador pela tela interna (`enviada_por` registra quem).
- Sem XML: RH informa os campos manualmente a partir do PDF.
- Arquivos em `storage/app/notas/{ano_mes}/{colaborador_id}/`, disco privado.

### 3. Leitor de XML — 16 h

`app/Services/NotaFiscal/`.

- Interface `LeitorNfse` com `suporta(\DOMDocument): bool` e `ler(): NotaLida` (DTO com número, série, chave, emissão, CNPJs, valores, retenções).
- `LeitorNfseNacional` (padrão nacional, ADN) primeiro; um leitor por layout municipal definido no kickoff (orçamento cobre até 2 layouts).
- Detecção automática do layout; layout desconhecido → "não foi possível ler", cai no preenchimento manual.
- Parse seguro: `LIBXML_NONET`, sem entidades externas.
- Fixtures com XML real (dados anonimizados) para cada layout.

### 4. Conferência, aprovação e recusa — 14 h

Ação `ConferirNota`, roda ao enviar e grava cada resultado:

| Conferência | Regra |
| --- | --- |
| Emissor | CNPJ emissor = PJ do colaborador |
| Tomador | CNPJ tomador = empresa contratante do colaborador |
| Valor | Valor bruto = total do fechamento, dentro da tolerância |
| Duplicidade | Chave ou CNPJ + número + série não existe em outra nota |
| Competência | Data de emissão compatível com a competência |

- Tudo certo → `enviada`; alguma falha → `divergente`, com a lista de divergências.
- Financeiro aprova (pode aprovar divergente com justificativa) ou recusa com motivo obrigatório.
- Aprovação dispara a fatia 6.

### 5. Alertas e download em lote — 8 h

- Comando diário: após o prazo, e-mail ao colaborador e ao responsável com notas não enviadas da competência.
- Tela de notas por competência com filtros (status, obra, colaborador).
- Download em lote (ZIP gerado em job, link por e-mail ou notificação na tela).

### 6. Título de contas a pagar no UAU — 20 h

- Ação `GerarTituloPagar`: só com fechamento aprovado e nota aprovada; vencimento pela regra configurada; valor líquido da nota.
- Job `EnviarTituloPagarUau`: chave de idempotência `titulo:{competencia}:{colaborador}`; antes de reenviar, consulta se o título já existe no UAU.
- De-para (`uau_mapeamentos`): empresa, obra, centro de custo, tipo de conta, plano de contas; tela de manutenção do de-para (admin/financeiro).
- Grava `uau_processo` retornado; status `enviado` ou `erro`, visível no monitoramento com reenvio.
- Anexo da nota (PDF/XML) ou comentário no processo, se a API permitir (spec 5.1, a confirmar).
- Após o envio, o fechamento fica "lançado" e a reabertura da competência passa a ser recusada.

### 7. Testes e homologação — 12 h

Deploy, ciclo completo com 3 a 5 prestadores reais em homologação, roteiro de validação.

## Testes obrigatórios

- Cada leitor de XML com as fixtures reais.
- XML com entidade externa é recusado.
- Cada conferência, com caso de sucesso e de falha.
- Título: não envia sem as duas aprovações; retentativa não duplica; erro 4xx não tenta de novo.
- Montagem do payload do título com respostas do UAU simuladas.

## Critério de aceite

1. Um colaborador envia a nota pelo portal; os dados lidos batem com o XML.
2. Nota com CNPJ errado vira `divergente`; recusada, o colaborador reenvia.
3. Nota aprovada gera o título no UAU de homologação com valor, vencimento, centro de custo e conta certos.
4. Derrubar o UAU durante o envio não duplica o título quando ele volta.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| Mais de 2 layouts municipais | Horas além do orçado | Layout extra orçado à parte; até lá, preenchimento manual |
| API do UAU sem anexo no processo | Nota não segue para o UAU | Link para a nota no histórico do título, ou comentário com a chave de acesso |
| Prestador sem XML (município só emite PDF) | Sem conferência automática | Preenchimento manual pelo RH, conferência roda sobre os campos digitados |
