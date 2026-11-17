# E2 · Fatia 2 (parte A) — Cadastro de PF e PJ · 10 h de 16

Entregue em 2026-11-18, na branch `e2/fatia-2-cadastro-pessoas`. A fatia 2 do [plano da Entrega 2](entrega-2-cadastros.md) se divide em A (esta: PF, PJ, responsável, dados bancários, histórico) e B (o colaborador, na branch `e2/fatia-2c-colaborador`, depois das equipes da 2b).

## O que foi feito

- Formulários em modal (padrão de `user.form`) para PF e PJ, abertos pelo botão "Nova pessoa" e pelo link "Editar" da lista.
- PJ exige a pessoa física responsável, escolhida por busca de nome ou CPF (`pessoa-fisica.buscar`, só pessoas ativas). A gravação chama `DefinirResponsavel` e audita a troca (`pessoa-juridica-responsavel-alterado`, com o nome antes e depois).
- Endereço em campos separados; o CEP preenche o resto pela ViaCEP, e a falha da consulta não impede o preenchimento manual.
- Dados bancários (várias contas, uma principal, chave PIX validada pelo tipo) na PF e na PJ, pela ação `SincronizarDadosBancarios`. A auditoria guarda só o número de contas, nunca conta nem chave.
- Detalhe de PF e PJ com responsável, bancos e as últimas 15 alterações (aba Histórico). `PessoaFisica` e `PessoaJuridica` usam o `Auditavel`, que audita só os campos do cadastro: a sincronização do UAU (`sincronizado_em`, `uau_alterado_em`) não gera registro.
- Permissões novas `pessoa-create` e `pessoa-edit`, dadas ao RH. Criar e editar são checados separadamente.

## Decisões

- **D2.1 — Pessoa nova nasce sem código do UAU.** A gravação no UAU é a fatia 4.
- **D2.2 — Aviso sobre o UAU.** Editar aqui uma pessoa vinda do UAU mostra um aviso: até a fatia 4, a sincronização diária pode desfazer a alteração, porque ainda não há envio nem detecção de conflito.
- **D2.3 — Validação no servidor.** CPF e CNPJ pelas rules da E1, normalizados para dígitos antes da checagem de unicidade; todas as mensagens vão juntas ao `axios-form` no campo `message`.

## Ainda não feito (fatia 2, parte B)

Busca avulsa no UAU (depende da API, fica para a fatia 4), colaborador (PF + PJ + cargo + empresa + obra, com vínculos sugeridos) e a regra de visibilidade do gestor. As contas e documentos da PJ seguem a mesma ação para o colaborador.
