# E4 · Fatia 9 — Fluxos de aprovação de férias por colaborador, equipe e função · 14 h

Escopo novo, pedido em 18/11/2026, fora das 584 h da proposta (horas a combinar com a ABL PRIME). Muda a fatia 3 da [Entrega 4](entrega-4-fatia-3-solicitacao-aprovacao.md): hoje há uma só lista de níveis para todos; passa a haver **vários fluxos**, e cada solicitação segue o fluxo que vale para o colaborador.

## Pedido

- Colaborador comum solicita e o gestor da equipe aprova.
- Colaborador de uma equipe específica solicita, o gestor aprova e depois o diretor aprova.
- Um gerente só precisa da aprovação do diretor. E assim por diante, por equipe, por função e por colaborador.

## Regras

- **Fluxo** (`ferias_fluxos`): nome, escopo e a lista ordenada de níveis. Escopos, do mais específico ao mais geral:
  1. `colaborador` — um colaborador;
  2. `equipe_cargo` — uma função dentro de uma equipe (ex.: gerentes da equipe X);
  3. `equipe` — toda uma equipe;
  4. `cargo` — uma função em qualquer equipe (ex.: todo gerente);
  5. `padrao` — quem não casou com nada. Existe sempre um, e só um; não pode ser excluído nem desativado.
- **Função = cargo do colaborador.** Não há campo novo: "gerente" é o cargo Gerente.
- **Qual fluxo vale:** o mais específico que casa com o colaborador na data. Colaborador em mais de uma equipe vigente: vale o de maior especificidade entre todas as equipes; empate vence o fluxo com mais níveis (o mais rigoroso), e persistindo o empate o de menor id.
- **Nível** (`ferias_niveis`, agora com `fluxo_id`): ordem, tipo e quem aprova.
  - `gestor_equipe` — gestores vigentes. Em fluxo de equipe ou de equipe e função, só os gestores daquela equipe; nos demais, os das equipes vigentes do colaborador.
  - `permissao` — quem tem a permissão (ex.: o perfil Diretor).
  - `usuario` — um usuário nomeado (novo).
- **Nível sem ninguém elegível** continua sendo pulado e registrado como escalada; depois do último nível vai para `ferias-aprovar-qualquer`, como hoje.
- **Fluxo congelado na solicitação:** a solicitação grava `fluxo_id` ao ser criada. Mudar um fluxo ou trocar o colaborador de equipe não muda o caminho das solicitações pendentes, só das novas. (Antes a fila era recalculada na hora; o nível atual passa a ser a ordem dentro do fluxo gravado.) Quem aprova dentro do nível continua sendo calculado na hora.
- **Tela** (`ferias-niveis-configurar`): lista de fluxos com escopo e níveis, formulário do fluxo (escopo, equipe, cargo, colaborador) e dos níveis. Mantém ao menos um nível ativo em cada fluxo.
- **Migração:** os níveis atuais viram o fluxo padrão. Nada muda para quem já usa o sistema.

## Decisões

- **D9.1 — Congelar o fluxo.** Evita aprovação "trocar de mãos" no meio. Custo: uma coluna na solicitação.
- **D9.2 — Empate vence o mais rigoroso.** Entre dois fluxos igualmente específicos, exigir mais aprovação é o erro seguro.
- **D9.3 — Sem fluxo de "aprovação automática".** Quem não precisa de aprovação não existe no pedido; se a ABL PRIME quiser, vira um tipo de nível. Fica como pergunta na pendência P26.

## Tarefas

1. Migration: `ferias_fluxos`, `ferias_niveis.fluxo_id` e `user_id`, `ferias_solicitacoes.fluxo_id`; carga do fluxo padrão; índice único (`fluxo_id`, `ordem`). Models, enums e factory.
2. `FluxoFerias` (resolve o fluxo do colaborador) e `AprovadoresFerias` por fluxo, com o lote.
3. `SolicitarFerias` grava o fluxo; `SincronizarFilaFerias` e `DecidirFerias` usam o fluxo da solicitação.
4. Tela de fluxos e níveis; seeders; menu e permissões inalterados.
5. Testes: os três exemplos do pedido, precedência, empate, congelamento, equipe com dois gestores, migração, N+1 do lote.
6. Painel, especificação e manual do RH.

## Situação (18/11/2026)

Feita e testada: 14 testes novos em `FluxosFeriasTest` (os três exemplos do pedido, precedência, empate, função só na equipe do fluxo, congelamento, equipe com dois gestores, usuário inativo, lote igual ao individual, tela e permissões) e a suíte completa verde. Validada no navegador (criar fluxo de equipe, nível de usuário específico).

- **Entregue além do plano:** o nível de um fluxo de equipe só chama os gestores daquela equipe; o fluxo padrão não pode ser excluído nem ficar sem nível ativo; nível de usuário inativo é pulado como qualquer nível sem aprovador.
- **Migração:** `2026_11_24_100001_create_ferias_fluxos`. Os níveis e as solicitações existentes ficam no fluxo padrão.
- **Pendente (ABL PRIME, P26):** os fluxos reais por equipe, função e colaborador, e quem é o diretor (usuário ou perfil).
