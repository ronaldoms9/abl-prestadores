# E3 · Fatia 4 — Vigência e alertas de contrato · 6 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 4 do [plano da Entrega 3](entrega-3-contratos-beneficios.md) e a regra de alertas do D2 do [README](README.md).

## O que foi feito

- **Vigência efetiva** (`VigenciaEfetiva`): como o contrato gerado é imutável, a prorrogação mora no aditivo (`dados.nova_vigencia_fim`). O fim que vale é o maior entre o do contrato e o dos aditivos de prorrogação gerados ou assinados.
- **Alerta diário** `alertas:contratos`, às 07:00: um e-mail-resumo para todo usuário ativo com `alerta-contratos`. Avisa nos **marcos de 30, 15 e 5 dias** (e no dia do vencimento) e **todo dia enquanto o contrato estiver vencido**. Marcos em `configuracoes[alertas.contratos].dias`. Sem nada a avisar, não envia; sem destinatário, registra no log e deixa o aviso no painel do administrador.
- **Renovar** (`RenovarContrato`): cria o rascunho do aditivo de prorrogação, começando no dia seguinte ao fim atual, com a nova data. Usa o modelo de aditivo para "prorrogação" ou, na falta, o genérico.
- Permissão nova: `alerta-contratos`.

## Decisões

- **D4.1 — Marcos exatos, não janela.** Diferente de documentos e qualificações (que listam tudo em até N dias), o contrato avisa só nos dias 30, 15 e 5, para não repetir o mesmo aviso por um mês. Vencido avisa todo dia.
- **D4.2 — Não entram** contrato sem fim (prazo indeterminado), de colaborador desligado ou já encerrado.
