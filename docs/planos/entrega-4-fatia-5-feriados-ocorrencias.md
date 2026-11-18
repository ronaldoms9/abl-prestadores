# E4 · Fatia 5 — Feriados e ocorrências · 8 h

Detalha a fatia 5 do [plano da Entrega 4](entrega-4-ferias-fechamento.md). Não depende de resposta da ABL PRIME: os feriados estaduais e municipais da lista dela entram depois, pela própria tela.

## Escopo

- **Feriados** (Férias e ausências › Feriados): lista por ano, cadastro, edição e exclusão. Nacional vale para todas as obras (sem obra); estadual e municipal exigem a obra a que se aplicam (a obra faz o papel do local de trabalho, como no resto do sistema). Botão "Importar nacionais" busca o ano na BrasilAPI e só acrescenta o que ainda não existe.
- **Ocorrências** (Férias e ausências › Ocorrências): lista por mês, cadastro, edição e exclusão de falta ou afastamento de um colaborador, com período (datas inclusive), "abona" e observação.
- **Competência fechada:** feriado ou ocorrência que toque mês fechado é recusado (usa `CompetenciaFechada`, ligado de verdade na fatia 7).
- Auditoria pelos models (`Auditavel`) e permissões novas: `feriado-list`, `feriado-gerir`, `ocorrencia-gerir`.

## Decisões

- **D5.1 — Sem DataTables.** Os dois cadastros são pequenos e filtrados por período (ano ou mês); uma tabela Blade com filtro basta.
- **D5.2 — Estadual e municipal sempre ligados a uma obra.** Evita guardar UF e município, que o cadastro de obras não tem hoje. Se a ABL PRIME quiser feriado estadual para todas as obras do estado, o RH cadastra uma linha por obra.
- **D5.3 — Unicidade:** não pode haver dois feriados com mesma data, abrangência e obra.
- **D5.4 — A BrasilAPI é conveniência:** falha de rede não bloqueia nada, a tela avisa e o RH cadastra à mão.
