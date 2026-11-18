# E5 · Fatia 1 — Migrations e models de notas e títulos · 4 h

Detalha a fatia 1 do [plano da Entrega 5](entrega-5-notas-contas-pagar.md). Tabelas da spec 3.5, com os acréscimos abaixo.

## Tabelas

- `notas_fiscais`: uma por fechamento (`fechamento_id` único). Reenvio de nota recusada **reaproveita a linha** (novos arquivos e dados, status volta a `enviada` ou `divergente`; o histórico fica na auditoria). Por isso `chave_acesso` pode ser única no banco. Sem chave, a duplicidade por CNPJ emissor + número + série é validada na ação (colunas nulas em parte dos casos). Acréscimos à spec: `origem_dados` (`xml` ou `manual`), `divergencias` (json, lista das conferências que falharam), `justificativa_aprovacao`, `decidido_por`, `decidido_em`.
- `nota_retencoes`: tributo (ISS, IRRF, INSS, PIS, COFINS, CSLL) e valor, lidos do XML ou digitados.
- `titulos_pagar`: espelho do que foi lançado no UAU (fatia 6, que depende do UAU); a tabela já nasce aqui. `chave_idempotencia` única (`titulo:{competencia}:{colaborador}`).

Valores em reais com 2 casas, como no fechamento. CNPJ só com dígitos.

## Models e enums

`NotaFiscal` (auditada, soft delete), `NotaRetencao`, `TituloPagar`; enums `StatusNota` (aguardando, enviada, divergente, aprovada, recusada), `TributoNota`, `StatusTituloPagar`, `OrigemDadosNota`. Morph map e vocabulário de auditoria atualizados.

## Pendente

A fatia 6 (título no UAU) usa `titulos_pagar` quando o UAU de homologação estiver disponível.
