# E3 · Fatia 8 — Benefícios do colaborador · 8 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 8 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Vínculo** (`VincularBeneficio`, `EncerrarBeneficioColaborador`): início, fim opcional e valor personalizado opcional, pela ficha do colaborador. Recusa colaborador desligado, início depois do último dia de trabalho, benefício inativo, valor não positivo, fim anterior ao início, início em competência fechada e período que se sobreponha a outro vínculo do mesmo benefício.
- **Valor efetivo** (`BeneficioVigente::valorEm`): o personalizado do vínculo ou, sem ele, o da tabela vigente naquele dia, em centavos; nulo quando não há vínculo ou valor no dia. `listaEm` devolve todos os benefícios do dia com a origem do valor. É o que o fechamento da E4 soma, dia a dia.
- **Benefícios padrão por cargo** (`SalvarBeneficiosPadraoCargo`): campo no formulário do cargo, só para quem tem `beneficio-gerir`. `AplicarBeneficiosPadrao` os dá ao colaborador na conclusão da admissão, sem duplicar nem pegar benefício inativo.
- Permissão nova: `colaborador-beneficio-edit`.

## Decisões

- **D8.1 — Troca de valor no meio do mês é natural:** o valor é lido por dia, e a tabela tem fim exclusivo.
- **D8.2 — O conjunto padrão do cargo é trocado inteiro,** reaproveitando a linha apagada ao voltar a incluir um benefício (par único cargo × benefício com exclusão lógica).
