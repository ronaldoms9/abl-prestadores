# E2 · Fatia 6 — Cargos, remuneração e reajustes · 20 h

Implementada em 2026-11-18, na branch `e2/fatia-6-cargos-remuneracao`. Detalha a fatia 6 do [plano da Entrega 2](entrega-2-cadastros.md) e a regra D5 do [README](README.md).

## O que foi feito

- **Cargos** (menu "Cargos e salários"): lista, formulário em modal, faixa mínima e máxima, desativar e excluir. Não exclui cargo com colaboradores. O formulário do colaborador passa a listar os cargos ativos.
- **Remuneração** no detalhe do colaborador: valor atual, histórico (de, até, valor) e o formulário de "Definir" (primeira remuneração) ou "Reajustar". Só aparece para quem tem `remuneracao-list`; a ação exige `remuneracao-reajustar`. Quem não vê remuneração também não vê "Remuneração definida" no histórico.
- **Reajuste individual** (`ReajustarRemuneracao`) e **em lote** (`PreverReajusteLote` e `AplicarReajusteLote`), com a tela "Reajuste em lote": critério (cargo ou lista), forma, valor e vigência; a **prévia** mostra atual, novo e diferença por pessoa, o total da folha e quem ficou de fora; **confirmar** grava tudo numa transação.
- **Evento `ReajusteAplicado`** (reajuste e linhas novas), disparado depois do commit, para a E3 gerar o aditivo.
- **Competência fechada:** a interface `CompetenciaFechada` recusa reajuste retroativo. Por ora nenhuma competência está fechada (`NenhumaCompetenciaFechada`); a E4 troca a implementação em `AppServiceProvider`.
- Permissões novas: `cargo-list`, `cargo-create`, `cargo-edit`, `remuneracao-list` e `remuneracao-reajustar`. RH recebe todas; Financeiro, as duas de consulta; Gestor, nenhuma.

## Decisões

- **D6.1 — Um só ponto grava remuneração.** `TrocarLinhaRemuneracao` encerra a linha vigente e cria a nova, e nunca altera o valor de uma linha. O fim é exclusivo, então "encerrar na véspera" do plano é "fim = início da nova": a antiga vale até o dia anterior.
- **D6.2 — O que "reajuste" significa.** Percentual é acréscimo percentual sobre o valor atual (5 = 5%). Valor é acréscimo em reais sobre o atual (200 = +R$ 200,00). Ambos positivos: redução de remuneração não existe nesta fatia. O percentual vale em 2 casas.
- **D6.3 — Dinheiro em centavos.** `Dinheiro` calcula em inteiros e arredonda meio para cima em 2 casas, para não acumular erro de ponto flutuante. Os formulários enviam `number` com ponto decimal; texto com vírgula ("5.000,50") também é lido.
- **D6.4 — Prévia confere com a confirmação.** A prévia devolve um hash de colaborador, valor atual e valor novo. A confirmação recalcula na transação e recusa se o hash mudou (alguém mexeu no meio) ou se alguma linha tem erro. Quem não tem remuneração vigente fica fora do lote e aparece listado.
- **D6.5 — Vigência.** A nova vigência precisa ser posterior ao início da linha atual. Correção de erro de digitação de uma linha é feita por novo reajuste, nunca por edição.
- **D6.6 — Faixa só avisa.** Remuneração fora da faixa do cargo gera aviso no detalhe, na prévia e na resposta do reajuste; não bloqueia.
- **D6.7 — Auditoria sem salário.** `remuneracao-definida` e `reajuste-aplicado` guardam tipo, forma, valor do reajuste e número de pessoas, nunca a remuneração de ninguém.

## Pendente

- Perfis com acesso à remuneração: a matriz da seção 6 da especificação ainda não foi validada pela ABL PRIME; os padrões acima são minha proposta.
- A ligação real de `CompetenciaFechada` com o fechamento mensal é da E4.
