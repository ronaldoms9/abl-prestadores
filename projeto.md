
Projeto: SaaS de assistente de WhatsApp para pequenos negócios.

Modelo: cliente paga mensalidade para ter um bot de atendimento automático
no WhatsApp dele. Ele mesmo configura o bot (sem escrever prompt técnico),
preenchendo premissas do negócio: tom de voz, produtos/serviços, FAQ,
horário, o que o bot pode e não pode responder.

Stack: WAHA (conexão com WhatsApp via WhatsApp Web, não-oficial) +
backend Laravel/PHP multi-tenant + fila (Redis/Horizon) + OpenRouter
para roteamento de LLM (modelo econômico por padrão, premium em planos
mais caros).

Fluxo: cliente final manda mensagem → WAHA recebe via webhook → fila
agrupa mensagens em rajada → monta prompt com config + histórico →
chama LLM via OpenRouter → responde no WhatsApp. Handoff pra humano
via gatilho.

Monetização: planos por número de conexão + volume de mensagens/tokens,
com teto e cobrança de excedente.

Riscos conhecidos: risco de ban do número (WAHA não é API oficial),
custo variável de tokens, reconexão de sessão como principal ponto de
suporte, LGPD sobre dados de conversas de terceiros.

Fase atual: validando modelo de negócio e arquitetura antes de começar
o MVP.
