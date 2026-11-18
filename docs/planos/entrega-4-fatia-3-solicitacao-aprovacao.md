# E4 · Fatia 3 — Solicitação e aprovação de férias em níveis · 18 h

Detalha a fatia 3 do [plano da Entrega 4](entrega-4-ferias-fechamento.md). As regras de aprovação são as do README (D7); os parâmetros de férias do PJ ainda não vieram da ABL PRIME e entram por configuração com padrão.

## Regras

- **Quem solicita:** `ferias-solicitar`. O gestor só para colaboradores das equipes que gere hoje (`Colaborador::visiveisPara`). O colaborador solicita pelo portal na E5.
- **Validações da solicitação:** colaborador ativo; período aquisitivo dele; gozo só depois de completado o período aquisitivo (`inicio` > fim do aquisitivo); dentro da vigência do colaborador (depois da admissão, até o desligamento); `dias` ≤ saldo do período menos os dias já reservados por solicitações pendentes; mínimo de dias por solicitação (`ferias.fracionamento_minimo_dias`, padrão 5, dispensado quando pede o saldo que sobrou); sem sobreposição com outra solicitação pendente ou aprovada do mesmo colaborador; mês sem competência fechada.
- **Aviso de conflito (não bloqueia):** outro colaborador da mesma equipe vigente ou da mesma obra com férias pendentes ou aprovadas no período.
- **Níveis:** `AprovadoresFerias` calcula, no dia da decisão, quem pode aprovar o nível atual: gestores vigentes das equipes vigentes do colaborador (nível `gestor_equipe`) ou usuários ativos com a permissão (nível `permissao`), sem o solicitante e sem o próprio colaborador. Nível sem ninguém é pulado e registrado como escalada; depois do último nível, ou sem nenhum nível, a solicitação vai a quem tem `ferias-aprovar-qualquer`; se nem isso, log + aviso no painel do admin.
- **Decisão:** qualquer aprovador do nível decide. Recusa encerra (observação obrigatória). Aprovação no último nível aprova a solicitação e baixa o saldo (`AjustarSaldoFerias`). Fila calculada na hora: trocar de equipe com solicitação pendente muda quem aprova, e os novos gestores recebem o e-mail na próxima sincronização.
- **Cancelamento:** pendente cancela sem mexer no saldo; aprovada devolve os dias, se nenhum mês do período estiver com competência fechada.
- **E-mails:** nova solicitação aos aprovadores do nível; decisão ao solicitante.
- **Tela de níveis** (`ferias-niveis-configurar`): ordem, tipo e permissão; mantém sempre ao menos um nível ativo.

## Decisões

- **D3.1 — Fila persistida só quando muda.** `AprovadoresFerias::resolver` é puro (usado para listar quem pode decidir); `SincronizarFilaFerias` grava as escaladas e avança `nivel_atual` na criação, em cada aprovação e antes de decidir.
- **D3.2 — Gozo depois do aquisitivo.** O padrão da CLT; se a ABL PRIME decidir diferente para PJ, a regra sai de `SolicitarFerias` num ponto só.
- **D3.3 — Reserva de saldo.** Pendentes reservam dias, para duas solicitações não prometerem o mesmo saldo; o saldo do período só baixa na aprovação.
