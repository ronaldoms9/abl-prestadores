# E3 · Fatia 7 — Benefícios com vigência e reajuste em lote · 14 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 7 do [plano da Entrega 3](entrega-3-contratos-beneficios.md) e a regra D5 do [README](README.md).

## O que foi feito

- **Benefícios** (menu Benefícios › Tabela de benefícios): lista com o valor atual, formulário em modal, tipo de cálculo (`fixo_mensal` ou `por_dia_trabalhado`), desativar e excluir. Não exclui benefício vinculado a colaborador.
- **Valores com vigência** (`TrocarValorBeneficio`, único ponto que grava): a linha vigente nunca é editada; ela encerra na data da nova vigência e uma nova começa. O modal "Valores" mostra o histórico e define o novo valor.
- **Reajuste em lote** (`PreverReajusteBeneficios` e `AplicarReajusteBeneficios`), com prévia e confirmação por hash, como o de remuneração: tudo ou nada, forma percentual ou em reais. Mexe na tabela, não nos valores personalizados dos colaboradores.
- **Competência fechada** recusa mudança de valor retroativa (liga na E4 pela mesma interface `CompetenciaFechada`).
- Permissões novas: `beneficio-list` e `beneficio-gerir`.

## Decisões

- **D7.1 — Mesma regra da remuneração** (D5, D6.1 a D6.5): vigência posterior ao início da linha atual, fim exclusivo, valor positivo, centavos inteiros.
- **D7.2 — O nome do benefício é único,** inclusive entre os excluídos (como o cargo).
- **D7.3 — Alteração de valor da tabela não gera aditivo.** Só vínculo e encerramento por colaborador geram (fatia 9). Se a ABL PRIME quiser aditivo para mudança de tabela, é um acréscimo pequeno.

## Pendente

- A lista de benefícios e os valores atuais são da ABL PRIME (RH). O `DemoSeeder` traz três de exemplo.
