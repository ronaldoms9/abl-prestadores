# E5 · Fatia 4 — Conferência, aprovação e recusa · 14 h

Detalha a fatia 4 do [plano da Entrega 5](entrega-5-notas-contas-pagar.md). A tolerância de valor e o prazo de envio ainda não vieram da ABL PRIME: entram por configuração, com padrão.

## Envio

`EnviarNota` recebe o fechamento (só com competência aprovada, "aguardando nota"), o XML e/ou o PDF e, se o XML faltar ou não puder ser lido, os campos digitados. Guarda os arquivos no disco privado em `notas/{ano_mes}/{colaborador_id}/`, grava a nota (e as retenções) e roda a conferência. Uma nota por fechamento: o reenvio de uma nota **recusada** reaproveita a linha. Quem enviou fica em `enviada_por` (o próprio colaborador no portal; RH ou financeiro em nome dele).

## Conferência (`ConferirNota`)

Roda ao enviar e grava as divergências em `divergencias`. Sem divergência a nota fica **enviada**; com, **divergente**.

| Conferência | Regra |
| --- | --- |
| Dados | número, data de emissão, CNPJs e valor bruto presentes |
| Emissor | CNPJ emissor = CNPJ da pessoa jurídica do colaborador |
| Tomador | CNPJ tomador = CNPJ da empresa contratante do colaborador |
| Valor | valor bruto = total líquido do fechamento, dentro da tolerância (`notas.tolerancia_valor`, padrão R$ 0,00) |
| Duplicidade | chave de acesso, ou CNPJ emissor + número + série, não existe em outra nota não recusada |
| Competência | emissão entre o 1º dia do mês da competência e o último dia do mês seguinte |

## Decisão

- `AprovarNota` (financeiro, `nota-decidir`): nota enviada ou divergente; divergente exige justificativa. Dispara o evento `NotaAprovada`, que a fatia 6 (título no UAU) vai ouvir.
- `RecusarNota`: motivo obrigatório; o colaborador recebe e-mail e pode reenviar.
- Nota aprovada ou recusada não volta atrás; a auditoria guarda quem decidiu e quando.

## Decisões

- **D4.1 — Uma nota por fechamento.** Nota complementar ou substituta no mesmo mês não existe nesta versão; se a ABL PRIME precisar, entra como nota adicional ligada ao fechamento.
- **D4.2 — Tolerância e prazo em `configuracoes`:** `notas.tolerancia_valor` (reais) e `notas.prazo_dia` (dia do mês seguinte, padrão 5, usado pelo alerta da fatia 5).
