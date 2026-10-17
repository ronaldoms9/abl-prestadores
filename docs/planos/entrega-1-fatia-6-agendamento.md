# E1 · Fatia 6 — Agendamento + "Sincronizar agora" · 5 h (3,5 h restantes) · semana 3

Detalha a fatia 6 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md) (depois da 8). Depende
da fatia 5 (a cadeia de conciliação) e da 8 (`Conciliacao::disparar()`, tabela
`sincronizacoes`, tela de integrações e `postAction()`).

A 8.1 já fez 1,5 h desta fatia:
- o registro de cada execução;
- a trava de uma sincronização por vez;
- a liberação de uma presa há mais de 1 h.

Restam 3,5 h, e a hora e meia volta para a folga da E1.

Ao fim desta fatia:
- a conciliação completa roda todo dia às 05:00 (horário de Brasília) pelo scheduler do Laravel;
- quem tem `integracao-sincronizar` dispara a mesma conciliação pelo botão "Sincronizar
  agora" na tela de integrações. Com outra em andamento, o pedido é recusado com aviso;
- a tela de integrações mostra as últimas sincronizações: origem, quem pediu, início, fim, situação e erro;
- o critério 4 da E1 fecha: pessoa alterada no UAU aparece alterada no dia seguinte ou na hora.

Branch `e1/fatia-6-agendamento`, a partir de `e1/fatia-8-monitoramento`. Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 6.1 | Comando `uau:sincronizar` e agendamento | 1 |
| 6.2 | Botão "Sincronizar agora" e quadro de sincronizações | 1,5 |
| 6.3 | Scheduler no Docker, verificação e documentação | 1 |
| | **Total** | **3,5** |

---

## Decisões

**D6.1 — O agendamento chama um comando, não a classe.**
- `php artisan uau:sincronizar` é fino (D5.1): chama `Conciliacao::disparar(OrigemSincronizacao::Agendada)`.
- O mesmo comando serve no servidor, à mão, sem tinker.
- Com outra sincronização em andamento, avisa e sai com sucesso (código 0). Não é erro: uma manual pode ter começado minutos antes.

**D6.2 — 05:00 no fuso da aplicação, configurável.**
- `config('uau.sincronizacao.horario')` (`UAU_SINCRONIZACAO_HORARIO`, padrão `05:00`),
  no fuso `APP_TIMEZONE` (`America/Sao_Paulo`). O horário fica no `.env` para a
  ABL PRIME mudar sem deploy de código.
- `->onOneServer()` e `->withoutOverlapping()`: se a E6 tiver dois servidores, só um dispara.

**D6.3 — Botão na tela de integrações.**
- `POST /integracoes/sincronizar` (`integracao.sincronizar`, `integracao-sincronizar`), resposta JSON `{success, message}`, pelo `postAction()` da 8.3.
- Registra a auditoria `sincronizacao-solicitada` (já no vocabulário), apontando para a sincronização criada.
- `SincronizacaoEmAndamento` → 409 com a mensagem ("Já há uma sincronização em andamento, iniciada às 04:58. Aguarde a conclusão.").
- A trava é a da 8.1 (tabela + `Cache::lock`), não só o `ShouldBeUnique`.
  O `ShouldBeUnique` sozinho descartaria o segundo pedido em silêncio, sem aviso na tela.

**D6.4 — Quadro "Sincronizações" na tela de integrações.**
- Mostra as 5 últimas: origem, quem pediu ("Agendada" quando não há autor), início, fim, situação e erro.
- Com uma em andamento, o botão fica desabilitado na tela, e o servidor recusa de qualquer jeito.
- O quadro aparece para quem tem `integracao-list`. O botão, só para quem tem `integracao-sincronizar`.
- O texto explica o que "concluída" quer dizer (D8.2): empresas e obras atualizadas; consultas de pessoas enviadas para a fila.

**D6.5 — Sem webhook.** O UAU da ABL PRIME não oferece webhook de saída (plano da E1).
A conciliação diária e o botão são o caminho. Vai para o roteiro de homologação (fatia 9).

**D6.6 — Scheduler no Docker de desenvolvimento.**
- Serviço `scheduler` no `docker-compose.yml`, com `php artisan schedule:work`, como o `horizon`.
- No servidor, é a linha do cron da fatia 9 (`* * * * * php artisan schedule:run`).

---

## 6.1 Comando e agendamento — 1 h

- `app/Console/Commands/UauSincronizar.php` (D6.1).
- `app/Console/Kernel.php::schedule()`: `uau:sincronizar` diário no horário do D6.2.
- `config/uau.php`: bloco `sincronizacao.horario`; `.env.example` comentado.

**Testes** (`tests/Feature/Uau/UauSincronizarTest.php`):
- o comando cria uma sincronização `agendada` e despacha a cadeia (`Bus::fake`);
- com outra em andamento, avisa e sai com 0, sem criar outra;
- `schedule:list` mostra `uau:sincronizar` às 05:00. Com `UAU_SINCRONIZACAO_HORARIO=06:30`, mostra 06:30.

## 6.2 Botão e quadro — 1,5 h

- `IntegracaoController::sincronizar()` (D6.3) e a rota, fora do grupo `integracao-list`, como a de reprocessar.
- Quadro na `integracoes/index.blade.php` (D6.4), com `with('criadoPor')`.
- O botão recarrega a página depois do pedido aceito, para o quadro mostrar a nova em andamento.

**Testes** (`tests/Feature/Integracoes/SincronizarAgoraTest.php`, `Bus::fake`):
- o botão cria uma sincronização `manual` com o autor, despacha a cadeia e registra `sincronizacao-solicitada`;
- um segundo pedido com a primeira em andamento recebe 409 com a mensagem, e só há uma;
- o quadro mostra origem, autor e situação, e o botão aparece só com a permissão (e desabilitado com uma em andamento);
- a rota entra em `PermissoesRotasTest`.

## 6.3 Scheduler, verificação e documentação — 1 h

- `docker-compose.yml`: serviço `scheduler` (D6.6).
- **No Docker:**
  - `php artisan schedule:list`;
  - `php artisan schedule:test --name=uau:sincronizar` dispara e conclui pelo Horizon;
  - o botão, clicado duas vezes, recusa o segundo.
- `docs/desenvolvimento.md`: agendamento, comando e scheduler.
- Roteiro de homologação (fatia 9): "sem webhook; a conciliação roda às 05:00 e pelo botão".

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Cron do scheduler no servidor | Fatia 9 |
| Alerta por e-mail quando a sincronização falha | E2+ (`alerta-integracao`) |
| Conciliar só as pessoas alteradas (`uau_alterado_em`) | E2, se a janela da madrugada não couber |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 6.1 e 6.2.
2. `schedule:list` mostra a conciliação diária às 05:00.
3. O botão despacha a cadeia, e um segundo clique com ela em andamento é recusado com aviso na tela.
4. A rota está em `PermissoesRotasTest`.

## Notas da implementação (2026-10-17)

- Sem `->environments()` no agendamento: os testes não rodam o scheduler, e o nome do
  ambiente de homologação é decidido na fatia 9.
- A rota `integracao.sincronizar` ficou fora do grupo `integracao-list`, como a de
  reprocessar, e pede só `integracao-sincronizar`. O quadro fica na tela de integrações, que pede `integracao-list`.
- `Auditoria::OBJETOS` ganhou a sincronização ("Sincronização · 3").
- **Verificado no Docker** (serviço `scheduler` novo, Horizon, Redis):
  - `schedule:list` mostra `0 5 * * *`;
  - `schedule:test --name=uau:sincronizar` gerou uma sincronização `agendada`, concluída em 2 s com o simulado;
  - um segundo `disparar()` seguido foi recusado com a mensagem.

## Riscos

| Risco | Saída |
| --- | --- |
| O cron do servidor não é instalado e nada roda às 05:00 | Item do roteiro da fatia 9; o quadro mostra a data da última sincronização, e a falta aparece no dia seguinte |
| A conciliação das 05:00 ainda está rodando quando o RH começa a usar | Medir a duração na homologação (o quadro mostra início e fim); antecipar o horário pelo `.env` |
