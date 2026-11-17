# E3 · Fatia 1 — Migrations e models · 8 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 1 do [plano da Entrega 3](entrega-3-contratos-beneficios.md) e a seção 3.3 da especificação.

## O que foi feito

- **Tabelas da spec 3.3:** `contrato_modelos`, `contratos`, `beneficios`, `beneficio_valores`, `colaborador_beneficios` e `admissao_checklist`. Acréscimos: `admissao_checklist_itens` (itens configuráveis, previsto no plano) e `cargo_beneficios` (benefícios padrão por cargo, fatia 8).
- **Models e factories** de todas, com autoria (`created_by`/`updated_by`), exclusão lógica e `Auditavel` onde há tela de cadastro. Apelidos de morph `contrato-modelo`, `contrato` e `beneficio`. Relações novas em `Colaborador` e `Cargo`.
- **Enums:** `TipoContrato`, `StatusContrato`, `TipoCalculoBeneficio` e `MotivoAditivo`.
- Vocabulário da auditoria ampliado com as ações de modelos, contratos, benefícios, admissão e desligamento.

## Decisões

- **D1.1 — Versões do modelo em linhas separadas.** `contrato_modelos.versao` e `modelo_anterior_id`: salvar um modelo em uso cria a linha seguinte e desativa a anterior (fatia 2).
- **D1.2 — Numeração sem colisão.** `contratos` guarda `ano` e `sequencia` além do `numero`, que é único (fatia 3).
- **D1.3 — O checklist guarda cópia.** `admissao_checklist` repete nome, chave, ordem e obrigatoriedade do item configurado, então mudar ou apagar o item não reescreve admissões em andamento.
- **D1.4 — Vigência por linha nova, como na remuneração.** `beneficio_valores` e `colaborador_beneficios` seguem o `TemVigencia` (fim exclusivo, fim nulo é a linha aberta).
- **D1.5 — Campos de apoio em `contratos`:** `motivo` (do aditivo), `dados` (valores e nomes que o aditivo usa nas variáveis), `gerado_em` e `assinado_em`.
