# E3 · Fatia 5 — Admissão com checklist · 14 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 5 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Itens configuráveis** (menu Admissão › Checklist de admissão): nome, ordem, obrigatório e ativo. Item automático (com chave) não se exclui.
- **Cinco itens automáticos** (`AdmissaoChecklistItemSeeder`, só cria o que falta): PJ gravada no UAU, remuneração definida, documentos obrigatórios enviados, qualificações obrigatórias do cargo em dia e contrato assinado anexado. O sistema reavalia sozinho, sem autor.
- **Iniciar** (`IniciarAdmissao`): copia os itens ativos para o colaborador em pré-admissão. Pode rodar de novo: só acrescenta o que falta.
- **Itens manuais** (`MarcarItemAdmissao`): o RH marca e desmarca, com autor, data e observação.
- **Concluir** (`ConcluirAdmissao`): exige todos os obrigatórios, passa o colaborador a ativo, grava a data de admissão e aplica os benefícios padrão do cargo. Tudo ou nada.
- **Tela** na ficha do colaborador: lista com o que falta em cada item automático, data de admissão e, se ainda não houver, campo de remuneração inicial.
- Tipos de documento ganham o campo **"Exigido na admissão"** (`documento_tipos.obrigatorio_na_admissao`).
- Permissão nova: `admissao-gerir`.

## Decisões

- **D5.1 — A remuneração costuma estar definida antes.** O contrato precisa dela, e o contrato assinado é item do checklist. Por isso "cria a primeira remuneração" do plano virou: se ainda não existe, a conclusão a grava com vigência na data de admissão, antes de conferir o checklist; se não existe e não foi informada, recusa.
- **D5.2 — "Documentos obrigatórios" é um marcador no tipo.** O E2 não tinha esse conceito. O documento precisa estar do dono certo para o tipo (PF, PJ ou colaborador; "todos" aceita qualquer) e, se o tipo tem validade, válido hoje.
- **D5.3 — Equipe não é item** (README, D7).

## Pendente

- A lista de itens manuais é da ABL PRIME (RH).
- **"PJ gravada no UAU" bloqueia PJ cadastrada só no sistema** até a gravação no UAU existir (fatia 4 da E2, que depende da API). Enquanto isso, o admin pode deixar o item opcional na tela de configuração.
