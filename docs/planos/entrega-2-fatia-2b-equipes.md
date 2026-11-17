# E2 · Fatia 2b — Equipes, gestores e vínculos · 6 h

Entregue em 2026-11-18, na branch `e2/fatia-2b-equipes`. Detalha a fatia 2b do [plano da Entrega 2](entrega-2-cadastros.md) e o D7 do [README](README.md).

## O que foi feito

- Tela **Equipes** (menu, lista, detalhe, formulário em modal, exclusão), com `equipe-list`, `equipe-create`, `equipe-edit` e `equipe-gerir`. RH recebe as três primeiras; o perfil Gestor recebe `equipe-list` e `equipe-gerir`; o Financeiro, `equipe-list`.
- **Gestores** da equipe no detalhe: lista os atuais e os anteriores, adiciona (a partir de uma data) e retira. Só entra usuário ativo com `equipe-gerir`, e o mesmo usuário não fica duas vezes aberto na mesma equipe.
- **Visibilidade (D7):** quem tem `equipe-gerir` e não pode criar nem editar equipes vê só as equipes que gere hoje; os demais com `equipe-list` veem todas. Equipe alheia dá 404 para o gestor.
- **Ações de vínculo** para uso da tela do colaborador (fatia 2c): `AdicionarGestor`, `VincularColaborador` e `EncerrarVinculo`.
- **Escopos reutilizados na E4:** `Equipe::gestoresVigentesEm($data)`, `Colaborador::equipesVigentesEm($data)` e `User::colaboradoresGeridosEm($data)`.
- Auditoria: `equipe-criado/alterado/excluido`, `equipe-gestor-adicionado` e `equipe-gestor-encerrado`.

## Decisões

- **D2b.1 — Fim exclusivo.** Retirar um gestor "a partir de 01/03" deixa ele valendo até 28/02. Encerrar antes do início é recusado.
- **D2b.2 — Não exclui equipe em uso.** Com gestor ou colaborador vigente, a exclusão é recusada com a mensagem pedindo para encerrar os vínculos antes. Sem vínculo vigente, a equipe sai por exclusão lógica e o histórico fica.
- **D2b.3 — Equipe inativa não recebe colaborador novo.** Ela continua visível e os vínculos antigos seguem valendo.

## Pendente

A aba "Equipes" na tela do colaborador (incluir e encerrar vínculos com data) depende da tela do colaborador, na fatia 2c.
