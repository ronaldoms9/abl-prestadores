# E3 · Fatia 2 — Modelos de contrato e variáveis · 16 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 2 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Tela de modelos** (menu Contratos › Modelos): lista, editor e exclusão. O editor é um `contenteditable` com barra de formatação (negrito, itálico, títulos, listas, alinhamento) e botão para editar o HTML, sem biblioteca externa; o painel lateral insere a variável onde está o cursor. A pré-visualização usa dados de exemplo e roda em um quadro isolado (`sandbox`).
- **Catálogo de variáveis** em código (`ContratoVariaveis`): colaborador, pessoa jurídica (com o responsável vigente), cargo, empresa, obra, remuneração (valor e por extenso), benefícios, contrato, aditivo, desligamento e datas. São 40 variáveis, cada uma com rótulo e exemplo.
- **Renderizador próprio** (`RenderizadorContrato`), nunca Blade: texto é escapado, só o que o sistema monta (lista de benefícios) entra como HTML. Variável sem dado vira `[chave sem valor]` e é listada em `faltantes`.
- **Sanitizador** (`SanitizadorHtml`): tira script, quadros, formulários, atributos de evento e endereços `javascript:`/`data:` (exceto imagem) antes de salvar.
- **Versionamento** (`SalvarContratoModelo`): salvar nome ou corpo de um modelo que já gerou contratos cria a versão seguinte e desativa a anterior; o tipo de um modelo em uso não muda; alternar "ativo" não cria versão.
- **Formatação BR:** `Extenso::moeda` ("mil e quinhentos reais e cinquenta centavos"; "um milhão de reais") e `Extenso::data` ("17 de novembro de 2026").
- Permissão nova: `contrato-modelo-gerir`.

## Decisões

- **D2.1 — Variável desconhecida bloqueia o salvamento** e a mensagem lista quais. Formatar parte de uma variável (`{{colaborador.<b>nome</b>}}`) também a torna desconhecida.
- **D2.2 — Sem biblioteca de editor.** Evita dependência de CDN e mantém o HTML simples para o dompdf. Se a ABL PRIME precisar de tabelas complexas no editor, troca-se por TinyMCE ou Trix.
- **D2.3 — `motivo` no modelo de aditivo.** Diz para que o aditivo serve (prorrogação, reajuste, mudança de cargo, benefícios ou qualquer motivo) e é o que a fatia 9 usa para escolher o modelo. Sem motivo, o modelo é genérico.

## Pendente

- Os modelos reais (Word/PDF do jurídico) entram na homologação. O `DemoSeeder` traz cinco modelos de exemplo, marcados como tal.
