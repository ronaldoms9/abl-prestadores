# E2 · Fatia 5 — Documentos com validade e alertas · 12 h

Implementada em 2026-11-19. Detalha a fatia 5 do [plano da Entrega 2](entrega-2-cadastros.md) e a regra de alertas do D2 do [README](README.md).

## O que foi feito

- **Tipos de documento configuráveis** (Configurações › Tipos de documento): nome, a quem servem (PF, PJ, colaborador ou todos) e se exigem validade. Tipo em uso é desativado, não excluído. O conjunto inicial (RG ou CNH, comprovante de residência, ASO, contrato social, cartão CNPJ, certidão negativa, alvará, contrato assinado e outros) só é criado com a tabela vazia, para a lista do RH da ABL PRIME a substituir e o seeder não recriar o que ela apagou.
- **Anexar, baixar e excluir** no detalhe de PF, PJ e colaborador. PDF, JPG ou PNG de até 10 MB, em disco privado (`storage/app/documentos/{tipo}/{id}/`), com o nome original guardado só no banco. O download passa por rota autorizada; documento de colaborador só para quem vê aquele colaborador (equipes que gere, D7). Validade obrigatória quando o tipo exige.
- **Alerta diário** `alertas:documentos`, às 06:30: um e-mail-resumo por usuário ativo com a permissão `alerta-documentos`, com os documentos vencidos e os que vencem em até 30 dias (a antecedência está em `configuracoes`, chave `alertas.documentos`, campo `dias`). Sem nada a avisar, não envia.
- **Sem destinatário (D2):** se ninguém ativo tem `alerta-documentos`, o comando não falha: grava no log e deixa um aviso no painel inicial, visível a quem edita perfis; o aviso some quando alguém passa a ter a permissão. O serviço `DestinatariosAlerta::comPermissao()` é o que os outros alertas (qualificações, férias) vão reaproveitar.
- **Indicador no colaborador:** faixa vermelha com os documentos vencidos dele, da PF e da PJ.
- Permissões novas: `documento-list`, `documento-edit`, `documento-tipo-gerir` e `alerta-documentos`. RH recebe as quatro; Financeiro, `documento-list`.

## Decisões

- **D5.1 — A validade é o último dia de uso.** Vence no dia seguinte; "a vencer" inclui hoje e o último dia da antecedência (hoje + 30).
- **D5.2 — Só o documento mais recente de cada tipo conta.** Anexar a renovação tira o antigo, já vencido, do alerta e do indicador. Sem isso, o alerta repetiria o mesmo vencido para sempre.
- **D5.3 — Donos fora de uso ficam de fora:** colaborador desligado, PF ou PJ inativa.
- **D5.4 — Exclusão é lógica e o arquivo fica** no disco, para a retenção; o histórico registra "Documento excluído" sem nome de arquivo.
- **D5.5 — Auditoria sem dado sensível:** `documento-anexado` guarda o tipo e a validade, nunca o nome nem o conteúdo do arquivo.

## Pendente

- A lista real de tipos e de quais têm validade é da ABL PRIME (RH); até ela chegar vale o conjunto inicial.
- O envio real de e-mail depende do SMTP e do DNS da homologação (`MAIL_MAILER=log` até lá).
- Em produção, o disco `local` (storage/app) precisa de backup, como o banco.
