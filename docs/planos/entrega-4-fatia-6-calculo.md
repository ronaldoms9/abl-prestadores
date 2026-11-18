# E4 · Fatia 6 — CalendarioTrabalho e CalculadoraFechamento · 26 h

Detalha a fatia 6 do [plano da Entrega 4](entrega-4-ferias-fechamento.md). Código em `app/Services/Fechamento/`. As regras abertas com a ABL PRIME entram por configuração, com o padrão do plano; nada aqui grava no banco.

## Regras (padrões assumidos, todos em `configuracoes`)

| Regra | Chave | Padrão |
| --- | --- | --- |
| Base do proporcional (spec 4.3) | `calculo.base_proporcional` | `corridos` (valor ÷ 30; mês inteiro vale o cheio) |
| Benefício fixo proporcional ao período ativo | `calculo.beneficio_fixo_proporcional` | sim; com `false`, vale o valor cheio do último dia ativo no mês |
| Sábado é dia trabalhado (spec 4.2) | `calculo.sabado_trabalhado` | não (um valor para todos; por obra ou colaborador só se a ABL PRIME pedir) |
| Férias reduzem a remuneração (spec 4.3) | — | não: a remuneração é a do período ativo, com ou sem férias |

## Dia trabalhado

`CalendarioTrabalho::diasTrabalhados(colaborador, inicio, fim)`: dias de segunda a sexta (mais o sábado, se configurado) no período, **menos** feriados que valem para o colaborador (nacionais e os da obra dele), **menos** os dias de férias aprovadas, **menos** os dias de falta ou afastamento **não abonados**. Falta ou afastamento abonado não tira o dia. Só o benefício por dia trabalhado usa esse número; a remuneração segue os dias corridos do período ativo.

## Cálculo do mês

`CalculadoraFechamento::calcular(colaborador, anoMes)` devolve um `ResultadoFechamento` (itens e totais) sem gravar nada.

- **Período ativo no mês:** de max(1º dia, admissão) a min(último dia, desligamento). Quem não esteve ativo no mês não tem fechamento.
- **Remuneração:** `RemuneracaoProporcional` sobre o período; um reajuste no meio do mês divide o mês em trechos, descritos na memória de cálculo. Um item.
- **Benefício fixo:** um item por trecho de valor (o valor muda no vínculo ou na tabela), proporcional aos dias do trecho.
- **Benefício por dia:** um item por trecho: dias trabalhados do trecho × valor do dia.
- **Ajustes avulsos do mês:** crédito soma (`ajuste`), débito subtrai (`desconto`, valor negativo).
- Tudo em centavos inteiros; o arredondamento é o do `RemuneracaoProporcional` (meio para cima, uma vez por trecho e mês). Os valores gravados na fatia 7 são os de `Dinheiro::decimal`.
- Cada item leva descrição legível (a memória de cálculo), quantidade, valor unitário, total e a origem (remuneração, vínculo de benefício ou ajuste).

## Testes

A matriz do plano da E4, com os valores escritos à mão no teste: mês cheio, admissão dia 15, desligamento dia 10, reajuste dia 16, reajuste de benefício dia 16, férias de 10 dias, feriado municipal da obra, falta abonada e não abonada, fevereiro com 28 e 29 dias, admissão e desligamento no mesmo mês; mais sábado trabalhado, benefício fixo cheio e ajustes. Valores a validar com o RH na homologação (fatia 8).
