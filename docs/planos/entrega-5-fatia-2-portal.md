# E5 · Fatia 2 — Portal do colaborador e telas de notas · 18 h

Detalha a fatia 2 (e a parte de telas das fatias 4 e 5) do [plano da Entrega 5](entrega-5-notas-contas-pagar.md).

## Portal (`portal-acessar`)

Mesmo `users` do painel, ligado à `pessoa_fisica_id`; só vê os próprios dados (tudo é consultado a partir do colaborador do usuário; nada recebe o id do colaborador). Quem tem `portal-acessar` e não tem `colaborador-list` cai no portal ao entrar. O menu mostra só o grupo "Meu portal".

- **Início:** saldo de férias, solicitações pendentes e notas a enviar.
- **Minhas férias:** períodos e saldo, solicitar (usa `SolicitarFerias` e a fila de aprovação da E4; o gestor da equipe recebe o e-mail), acompanhar e cancelar o que está pendente.
- **Minhas notas:** competências com fechamento aprovado, valor esperado e situação de cada nota; enviar ou reenviar (nota recusada mostra o motivo).
- **Envio:** XML e/ou PDF. O XML é lido na hora e os dados lidos aparecem para o colaborador conferir; se não der para ler, ou se o município só emite PDF, ele (ou o RH) digita os campos.

## Telas internas

- **Notas fiscais** (`nota-list`): por competência, com filtros de situação, obra e colaborador; detalhe com divergências e arquivos; RH e financeiro enviam em nome do colaborador (`nota-enviar`); o financeiro aprova (com justificativa se divergente) ou recusa com motivo (`nota-decidir`).
- **Download em lote:** o botão gera um ZIP em job (PDF e XML das notas não recusadas da competência) e avisa por e-mail com link assinado de 24 horas.

## Alertas (fatia 5)

Comando diário às 08:00: para cada competência aprovada cujo prazo (`notas.prazo_dia` do mês seguinte, padrão dia 5) já passou, avisa por e-mail o colaborador que ainda não tem nota válida (nunca enviou ou foi recusada) e manda um resumo a quem tem `alerta-notas`.

## Decisões

- **D2.1 — Quem não tem colaborador ligado ao usuário recebe 403** com a explicação, em vez de tela vazia.
- **D2.2 — Download de arquivo:** o colaborador baixa só os próprios; `nota-list` baixa de qualquer um.
- **D2.3 — O ZIP não guarda recusadas.**

## Permissões novas

`portal-acessar`, `nota-list`, `nota-enviar`, `nota-decidir`, `alerta-notas`.
