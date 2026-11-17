# E2 · Fatia 2 (parte B) — Colaborador · 6 h de 16

Entregue em 2026-11-18, na branch `e2/fatia-2c-colaborador`. Fecha a fatia 2 do [plano da Entrega 2](entrega-2-cadastros.md), exceto a busca avulsa no UAU, que depende da API (fatia 4).

## O que foi feito

- Tela **Colaboradores** (menu, lista com busca por nome, CPF, razão social ou CNPJ e filtro de situação, detalhe, formulário em modal). Permissões `colaborador-list`, `colaborador-create` e `colaborador-edit`: RH tem as três; Gestor e Financeiro, a de consulta.
- Formulário: PF (busca por nome ou CPF, fixa depois de criada), PJ (busca por razão social, nome fantasia ou CNPJ), cargo, empresa e obra (a obra faz o papel de centro de custo e só lista as da empresa escolhida). Nasce em **pré-admissão**.
- **Vínculos sugeridos:** ao escolher a PF, o formulário oferece as PJs sugeridas pela carga da planilha e ainda não usadas. Gravar com uma delas marca `utilizado_em`.
- Regra da PF: um colaborador aberto (pré-admissão ou ativo) por vez, com a PF travada na gravação para dois envios simultâneos não abrirem dois. Com o anterior desligado, abre outro e o histórico com várias PJs fica.
- A obra precisa pertencer à empresa escolhida, no servidor também.
- **Aba Equipes** no detalhe: incluir e retirar vínculos com data, pelas ações da fatia 2b.
- **Histórico** (auditoria do `Colaborador`: PJ, cargo, empresa, obra, situação e datas).
- **Visibilidade (D7):** o gestor (com `equipe-gerir` e sem `colaborador-edit`) vê na lista e no detalhe só os colaboradores das equipes que gere hoje; os outros, todos.

## Decisões

- **D2c.1 — Sem exclusão de colaborador.** Admissão e desligamento são da E3; até lá o registro só muda de situação.
- **D2c.2 — Cargo opcional por enquanto.** A tela de cargos é da fatia 6 da E2; o formulário já lista os cargos ativos que existirem e avisa quando não há nenhum.
- **D2c.3 — PF e PJ precisam estar ativas** para entrar em um colaborador novo.
