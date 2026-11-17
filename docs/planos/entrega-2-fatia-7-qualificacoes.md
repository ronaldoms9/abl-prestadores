# E2 · Fatia 7 — Qualificações, matriz e alertas · 12 h

Implementada em 2026-11-19. Detalha a fatia 7 do [plano da Entrega 2](entrega-2-cadastros.md) e a regra de alertas do D2 do [README](README.md). Fecha a Entrega 2 no que não depende do UAU.

## O que foi feito

- **Tipos de qualificação** (menu Qualificações): nome, categoria (curso, certificação, treinamento de segurança ou habilitação) e validade em meses, em branco quando não vence. Tipo com registro ou na matriz é desativado, não excluído.
- **Matriz por cargo × qualificação**, em grade: cada cruzamento é "—" (não exige), "Opcional" ou "Obrigatória". Salva só o que mudou e grava um registro de auditoria com o número de células alteradas.
- **Qualificações do colaborador** no detalhe: tipo, data de conclusão, instituição e certificado opcional (PDF, JPG ou PNG, em disco privado, baixado por rota autorizada). A **validade é calculada**: conclusão mais os meses do tipo, sem estourar o fim do mês (31/01 + 1 mês = 28/02). A tela mostra "Vale até" antes de registrar. Faixa vermelha com as obrigatórias ausentes e vencidas do cargo.
- **Lista de pendências** (menu Qualificações › Pendências): colaboradores com obrigatória ausente, vencida ou vencendo em até 30 dias, com filtro por cargo. O gestor vê só os colaboradores das equipes que gere.
- **Alerta diário** `alertas:qualificacoes`, às 06:45: um e-mail-resumo para todo usuário ativo com `alerta-qualificacoes`, com ausentes, vencidas e a vencer. Mesmo formato, mesma regra de "sem destinatário" e mesma antecedência configurável (`configuracoes`, chave `alertas.qualificacoes`) do alerta de documentos.
- Permissões novas: `qualificacao-list`, `qualificacao-edit`, `qualificacao-tipo-gerir` e `alerta-qualificacoes`. RH recebe as quatro; Gestor e Financeiro, `qualificacao-list`.

## Decisões

- **D7.1 — Só as obrigatórias geram pendência e alerta.** "Opcional" na matriz é informativo; uma qualificação opcional vencida não alerta ninguém.
- **D7.2 — Vale a mais recente de cada tipo** (maior data de conclusão). Registrar a renovação tira a antiga, vencida, da lista e do alerta.
- **D7.3 — A validade é o último dia de uso**, como nos documentos: vence no dia seguinte, e "a vencer" inclui hoje e o último dia da antecedência.
- **D7.4 — Só colaboradores em pré-admissão ou ativos, com cargo,** e só tipos ativos entram. Sem cargo não há exigência.
- **D7.5 — Conclusão futura é recusada;** a de hoje vale.
- **D7.6 — Linha da matriz retirada é reaproveitada** ao voltar a exigir, por causa do par único cargo × tipo com exclusão lógica.
- **D7.7 — Auditoria sem o nome do certificado:** guarda o tipo, a conclusão e a validade.

## Pendente

- A matriz real de qualificações por cargo é da ABL PRIME (RH). A tela está pronta para recebê-la; até lá a grade começa vazia e ninguém tem pendência.
- O envio real de e-mail depende do SMTP e do DNS da homologação.
