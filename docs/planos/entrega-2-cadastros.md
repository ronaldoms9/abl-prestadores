# Entrega 2 — Cadastros completos · 104 h · semanas 4–6

Ao fim da E2, o RH cadastra e edita PF e PJ no sistema, liga cada colaborador
à sua PJ, cargo, obra e equipes, e as pessoas são gravadas no UAU pela fila.
Documentos, remuneração com vigência, reajustes e qualificações passam a viver
aqui, com alertas de vencimento por e-mail.

## Antes de começar

| Item | Responsável | Bloqueia |
| --- | --- | --- |
| Endpoint de gravação/alteração de PF e PJ no UAU, com campos obrigatórios | Nós (Swagger) + ABL PRIME | Fatia 4 |
| Matriz de permissões da spec seção 6 validada | ABL PRIME | Fatias 2, 6, 7 |
| Lista de tipos de documento e quais têm validade | ABL PRIME (RH) | Fatia 5 |
| Lista de qualificações por cargo (matriz) | ABL PRIME (RH) | Fatia 7 |
| Serviço de consulta de CNPJ (BrasilAPI gratuita, ou pago se a ABL PRIME preferir) | Nós | Fatia 3 |

## Fatias

| # | Fatia | Horas |
| --- | --- | --- |
| 1 | Migrations e models da spec 3.2 | 10 |
| 2 | Cadastro de PF, PJ e colaborador | 16 |
| 2b | Equipes, gestores e vínculos | 6 |
| 3 | Validação de CPF/CNPJ e consulta de CNPJ | 6 |
| 4 | Gravação de pessoas no UAU | 18 |
| 5 | Documentos com validade e alertas | 12 |
| 6 | Cargos, remuneração e reajustes | 20 |
| 7 | Qualificações, matriz e alertas | 12 |
| 8 | Homologação | 4 |
| | **Total** | **104** |

### 1. Migrations e models — 10 h

Tabelas da spec 3.2: `colaboradores`, `dados_bancarios`, `documentos`, `cargos`, `remuneracoes`, `reajustes`, `qualificacao_tipos`, `cargo_qualificacoes`, `colaborador_qualificacoes`.

Acréscimos à spec:
- `equipes` (nome, descricao, ativo), `equipe_gestores` (equipe_id, user_id, inicio, fim) e `colaborador_equipes` (colaborador_id, equipe_id, inicio, fim) — README, D7.
- `configuracoes` (chave, valor json) — antecedência dos avisos e demais parâmetros. Destinatários de alertas não são configurados: vão ao perfil responsável.
- `remuneracoes`: índice único parcial evitando duas linhas abertas (`vigencia_fim` nulo) para o mesmo colaborador; garantir na ação, já que o MySQL não tem índice parcial.
- `pessoas_fisicas`/`pessoas_juridicas`: `uau_alterado_em` para detectar conflito (fatia 4).

### 2. Cadastro de PF, PJ e colaborador — 16 h

- Liberar edição nas telas da E1 (PF e PJ), mantendo a lista e o detalhe.
- Formulário de PF: dados pessoais, endereço (json com CEP preenchendo via ViaCEP), contatos.
- Formulário de PJ: CNPJ, razão social, nome fantasia, regime tributário, inscrição municipal, endereço.
- Dados bancários (morph) em PF e PJ: vários por pessoa, um `principal`; chave PIX validada por tipo.
- Colaborador: liga PF + PJ + cargo + empresa + obra + centro de custo; status `pre_admissao` ao criar. Uma PF pode ter histórico com mais de uma PJ (um colaborador ativo por vez).
- Histórico de alterações na aba "Histórico" (activitylog com os campos alterados).
- Policy: RH/admin editam; financeiro consulta tudo; gestor consulta só os colaboradores das equipes que gere hoje (vínculos vigentes).

### 2b. Equipes, gestores e vínculos — 6 h

- CRUD de equipes (independentes da obra).
- Gestores da equipe: adicionar (só usuários com perfil `gestor`) e encerrar, com data; lista mostra atuais e anteriores.
- Equipes do colaborador: aba na tela do colaborador para incluir e encerrar vínculos com data; um colaborador pode estar em várias equipes ou em nenhuma.
- Escopos reutilizados na E4: `Colaborador::equipesVigentesEm($data)`, `Equipe::gestoresVigentesEm($data)`, `User::colaboradoresGeridosEm($data)`.
- Equipe não é item obrigatório da admissão (E3).

### 3. Validação de CPF/CNPJ e consulta de CNPJ — 6 h

- Rules `Cpf` e `Cnpj` (dígitos verificadores) em `app/Rules`.
- Botão "Buscar CNPJ" na PJ: consulta BrasilAPI, preenche razão social, endereço e situação cadastral; avisa se a situação não for "ativa".
- Resposta em cache por 24 h; falha da consulta não impede o cadastro manual.

### 4. Gravação de pessoas no UAU — 18 h

- Ações `SalvarPessoaFisica` / `SalvarPessoaJuridica`: gravam local e despacham `EnviarPessoaUau` após o commit (`afterCommit`).
- Job: monta o payload pelo mapper, chave de idempotência `pessoa:{tipo}:{id}:{updated_at}`, cria no UAU se não houver `uau_codigo`, altera se houver; grava o código retornado.
- Conflito (spec 5.2): antes de alterar, consulta a pessoa no UAU; se mudou depois de `sincronizado_em`, não envia, marca o log como `conflito` e mostra a diferença campo a campo na tela de monitoramento, com as opções "usar o do sistema" e "usar o do UAU".
- Selo de status na tela da pessoa: sincronizada, pendente, erro, conflito.
- Importação horária da E1 passa a respeitar registros com envio pendente (não sobrescreve).

### 5. Documentos com validade e alertas — 12 h

- Upload em `storage/app/documentos/{tipo}/{id}` (disco privado), download por rota autorizada.
- Tipos de documento configuráveis; validade obrigatória quando o tipo exige.
- Comando diário `alertas:documentos`: e-mail a todos os usuários ativos do perfil RH com documentos vencendo em 30 dias e vencidos (um resumo por dia, não um e-mail por documento). Serviço `DestinatariosAlerta::doPerfil('rh')` reaproveitado por todos os alertas.
- Indicador na tela do colaborador: documentos vencidos.

### 6. Cargos, remuneração e reajustes — 20 h

- CRUD de cargos com faixa mínima e máxima; aviso (não bloqueio) quando a remuneração sai da faixa.
- Remuneração com vigência: a tela mostra a linha atual e o histórico; nunca editar uma linha (D5).
- Ação `ReajustarRemuneracao` (individual): encerra a linha vigente na véspera da nova vigência e cria a nova, ligada ao `reajuste`.
- Reajuste em lote: critério (cargo ou lista), forma (percentual ou valor), vigência → tela de prévia com valor atual, novo e diferença por pessoa → confirmar grava tudo em uma transação.
- Reajuste retroativo a mês já fechado é recusado (a regra fica pronta; a checagem de competência fechada liga na E4).
- Gancho `ReajusteAplicado` (evento) para a E3 gerar o aditivo.

### 7. Qualificações, matriz e alertas — 12 h

- CRUD de tipos de qualificação (categoria, validade em meses).
- Matriz cargo × qualificação (tela em grade, marca obrigatória).
- Qualificações do colaborador com certificado anexo; validade calculada a partir da conclusão.
- Lista "pendentes": colaboradores sem uma qualificação obrigatória do cargo ou com ela vencida.
- Comando diário `alertas:qualificacoes`, mesmo formato do de documentos.

### 8. Homologação — 4 h

Deploy, seed dos tipos de documento e qualificação informados pela ABL PRIME, roteiro de validação.

## Testes obrigatórios

- Rules de CPF e CNPJ (válidos, inválidos, com máscara).
- Reajuste individual: encerra a linha anterior na véspera; não deixa duas abertas.
- Reajuste em lote por cargo e por lista, percentual e valor; arredondamento em 2 casas.
- Remuneração vigente em uma data qualquer (base da E4).
- Envio ao UAU: cria, altera, não duplica na retentativa, para em conflito.
- Lista de pendências de qualificação.
- Vínculos de equipe e de gestor vigentes em uma data; gestor só vê colaboradores das equipes que gere.

## Critério de aceite

1. RH cadastra uma PF e sua PJ; ambas aparecem no UAU de homologação.
2. Alterar a PJ no sistema altera no UAU; alterar a mesma PJ no UAU e depois no sistema gera um conflito visível.
3. Reajuste em lote de um cargo mostra a prévia e, confirmado, cria o histórico certo.
4. Um documento com validade em 10 dias aparece no e-mail de alerta do dia seguinte, recebido por todos os usuários do RH.
5. Um gestor entra e vê só os colaboradores das equipes que ele gere.

## Riscos

| Risco | Efeito | Saída |
| --- | --- | --- |
| UAU exige campos que o RH não tem (ex.: códigos internos) | Envio recusado | Levantar os obrigatórios no início da E2 e pôr defaults no de-para |
| UAU não permite consultar `data de alteração` | Conflito não detectável | Comparar campo a campo com o último payload recebido |
