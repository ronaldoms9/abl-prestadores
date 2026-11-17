# E3 · Fatia 9 — Reajuste gera aditivo · 6 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 9 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Reajuste** (`ReajusteAplicado`, da E2): o listener `GerarAditivosDeReajuste`, em fila, cria um **rascunho de aditivo** para cada colaborador reajustado que tem contrato em vigor, com o valor antigo e o novo.
- **Mudança de cargo** de quem está ativo (`CargoDoColaboradorAlterado`, disparado por `SalvarColaborador`) e **benefício incluído ou encerrado** pela ficha (`BeneficiosDoColaboradorAlterados`) geram o mesmo, pelo `GerarAditivoDeAlteracao`.
- **`CriarRascunhoDeAditivo`** concentra a regra: escolhe o contrato pai e o modelo de aditivo do motivo (ou o genérico) e **não lança erro** quando não cabe aditivo; devolve o motivo (`sem_contrato`, `desligado`, `sem_modelo`, `duplicado`). Uma chave por alteração impede rascunho em duplicidade.
- **Tela "Aditivos pendentes"** (menu Contratos): lista os rascunhos com o detalhe (valor antigo → novo, cargo, benefício), seleção e **geração em massa** (`GerarAditivosEmMassa`). Cada um sai na sua transação: os que faltam dado ficam de fora, com a mensagem, e não barram os demais. Até 200 por vez.

## Decisões

- **D9.1 — O aditivo nasce rascunho.** Nada é numerado nem vai a PDF sem o RH conferir.
- **D9.2 — A admissão não gera aditivo:** os benefícios padrão do cargo entram junto com o contrato.
- **D9.3 — Quem não tem contrato em vigor não recebe rascunho** (por exemplo, colaborador importado do UAU sem contrato no sistema). O resultado fica no log.
