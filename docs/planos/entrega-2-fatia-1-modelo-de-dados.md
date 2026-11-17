# E2 · Fatia 1 — Migrations e models · 10 h

Entregue em 2026-11-18, na branch `e2/fatia-1-modelo-de-dados`. Detalha a fatia 1 do [plano da Entrega 2](entrega-2-cadastros.md).

## O que foi criado

- Tabelas: `cargos`, `colaboradores`, `equipes`, `equipe_gestores`, `colaborador_equipes`, `dados_bancarios`, `documentos`, `reajustes`, `remuneracoes`, `qualificacao_tipos`, `cargo_qualificacoes`, `colaborador_qualificacoes`, `configuracoes`. `uau_alterado_em` entra em `pessoas_fisicas` e `pessoas_juridicas`.
- Um model, uma factory e (onde há conjunto fechado) um enum por tabela: `StatusColaborador`, `TipoContaBancaria`, `TipoChavePix`, `CategoriaQualificacao`, `TipoReajuste`, `FormaReajuste`.
- Relações em PF e PJ: `colaboradores`, `dadosBancarios` e `documentos` (morph).
- Apelidos de morph: `colaborador`, `equipe` e `cargo`, além dos já existentes.

## Decisões

- **D1.1 — Vigência de fim exclusivo.** O trait `TemVigencia` (escopos `vigentes()` e `vigentesEm($data)`) vale para equipes, gestores, remuneração e o responsável da PJ. A linha encerrada em 10/05 vale até 09/05; a nova começa em 10/05. Em cada dia vale uma só, o que a E4 precisa para refazer qualquer mês.
- **D1.2 — Regras que o MySQL não garante.** Um colaborador aberto (`pre_admissao` ou `ativo`) por PF, uma remuneração aberta por colaborador e um responsável aberto por PJ ficam nas ações, não em índice. `StatusColaborador::abertos()` serve de base.
- **D1.3 — Exclusão.** PF, PJ e cargo de um colaborador não são apagados em cascata (`restrictOnDelete`, ou `nullOnDelete` em cargo, empresa e obra). Equipe e gestor sem uso podem sair.
- **D1.4 — Sem auditoria automática ainda.** O trait `Auditavel` entra nos models que têm tela, na fatia 2.

## Pronto quando

Cada factory cria um registro válido com autoria; PF com mais de uma PJ no histórico; vigência por data; matriz de exigências sem par repetido (`tests/Feature/Models/CadastrosE2Test.php`).

## Não verificado

As migrations rodaram só em SQLite (testes). Falta `migrate:fresh --seed` em MySQL, antes de fechar a Entrega.
