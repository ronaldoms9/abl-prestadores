# E1 · Fatia 8 — Monitoramento das integrações · 6 h · semana 3

Detalha a fatia 8 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md) (8 antes da 6). Depende
da fatia 4 (`integracao_logs`, `OperacaoUau`), da 5 (jobs e a cadeia
`Conciliacao::cadeia()`) e da 5b (padrão de tela com resultado).

Ao fim desta fatia:
- quem tem `integracao-list` vê cada chamada ao UAU, com filtros por situação,
  operação, direção e período, e abre o detalhe com o que foi enviado e recebido;
- uma falha que já foi resolvida por uma tentativa posterior aparece como resolvida,
  e só as falhas sem solução contam no indicador;
- quem tem `integracao-reenviar` reprocessa uma falha: o job daquela importação volta para a fila `uau`;
- o topo da tela mostra as falhas sem solução das últimas 24 h e a data da última
  sincronização concluída;
- cada execução da cadeia de conciliação fica registrada (`sincronizacoes`), o
  que a fatia 6 usa para o agendamento e o botão "Sincronizar agora".

Branch `e1/fatia-8-monitoramento`, a partir de `e1/fatia-5b-planilha`. Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 8.1 | Registro das sincronizações (`sincronizacoes`) | 1,5 |
| 8.2 | Lista de `integracao_logs`, filtros e detalhe | 2 |
| 8.3 | Reprocessar uma falha | 1 |
| 8.4 | Indicador no topo | 1 |
| 8.5 | Verificação e documentação | 0,5 |
| | **Total** | **6** |

---

## Decisões

**D8.1 — Uma tabela para as execuções da conciliação.** A fatia 5 deixou para a
6 onde guardar "a última conciliação bem-sucedida". O indicador desta fatia precisa
dela, e o "Sincronizar agora" da 6 precisa saber se há uma em andamento. Opções:

| Opção | Por que não / por que sim |
| --- | --- |
| Cache (`Cache::forever`) | Some num `cache:clear` ou troca de Redis; sem histórico |
| Derivar de `integracao_logs` (último sucesso de `obras-ativas`) | Não diz se a cadeia inteira terminou nem quem pediu |
| **Tabela `sincronizacoes`** | Histórico, origem (agendada ou manual), quem pediu, situação e erro. Escolhida |

- Colunas: `origem` (`agendada`/`manual`), `status` (`em_andamento`/`concluida`/`falhou`),
  `iniciada_em`, `concluida_em`, `erro`, `$table->auditoria()` (`created_by` = quem pediu).
- O nome é "sincronização" porque é o termo da tela ("Sincronizar agora"). A classe
  `App\Integrations\Uau\Conciliacao` continua sendo a cadeia.

**D8.2 — `Conciliacao::disparar()` registra e despacha.**
- Cria a `Sincronizacao` em andamento.
- Despacha a cadeia da fatia 5 com um último elo, `ConcluirSincronizacaoJob`, que marca `concluida`.
- `->catch()` da cadeia marca `falhou` com a mensagem. Um elo que falha interrompe os seguintes (fatia 5).
- **"Concluída" quer dizer:** empresas e obras importadas e uma consulta despachada
  por pessoa. As consultas de pessoa seguem na fila depois disso, cada uma com seu
  log. Esperar todas exigiria `Bus::batch` e a tabela `job_batches`, o que não se paga na E1.
- **Uma por vez:** se houver uma em andamento iniciada há menos de 1 h (o `uniqueFor`
  dos jobs), `disparar()` lança `SincronizacaoEmAndamento`. Uma em andamento mais antiga
  é dada como interrompida (`falhou`, "Interrompida: o worker parou antes de concluir") e a nova segue.
  O botão da fatia 6 mostra esse aviso.

**D8.3 — Falha resolvida.** Uma linha de erro está resolvida quando há uma chamada
posterior com sucesso da mesma operação e da mesma `chave_idempotencia` (as duas
nulas valem como iguais: empresas, obras e autenticação).
- Escopo `IntegracaoLog::semSolucao()`, com `whereNotExists`. Índice novo
  (`operacao`, `status`, `executado_em`) para a subconsulta.
- A tela mostra "Erro" ou "Erro resolvido". O filtro de situação tem as duas opções.
- O indicador e o "Reprocessar" olham só as sem solução.

**D8.4 — O que dá para reprocessar.** Só falhas sem solução, da direção saída:

| Operação | Despacha |
| --- | --- |
| `empresas-ativas` | `ImportarEmpresasJob` |
| `obras-ativas` | `ImportarObrasJob` |
| `pessoa-por-documento` | `ImportarPessoaJob(payload.cpf_cnpj)` |
| `autenticar`, `versao` | nada: não são importação. A próxima chamada autentica de novo |

- O job vai para a fila `uau` e respeita o `ShouldBeUnique`: se o mesmo já estiver
  na fila, ele cobre o pedido. A resposta diz "enviado para a fila", não "resolvido".
- Registra a auditoria `integracao-reprocessada` (ação nova no vocabulário) com o id do log.
- O resultado aparece como uma linha nova em `integracao_logs`, que marca a antiga como resolvida (D8.3).

**D8.5 — Indicador no topo.**
- Para quem tem `integracao-list`: um sino com o número de falhas sem solução nas últimas 24 h (some quando é zero).
- O menu do sino mostra:
  - "N falhas sem solução nas últimas 24 h";
  - "Última sincronização concluída: dd/mm/aaaa hh:mm" (ou "nenhuma");
  - um link para a lista filtrada.
- Calculado por um view composer do `_navbar`, com cache de 60 s por permissão,
  não por usuário: o número é o mesmo para todos.

**D8.6 — Detalhe sem vazar segredo.** O detalhe mostra `payload` e `resposta` como
gravados: o `Sanitizador` da fatia 4 já trocou senha, token e dados bancários por
`[oculto]`. Resposta grande (lista de empresas e obras) aparece numa caixa com rolagem, sem cortar.

---

## 8.1 Sincronizações — 1,5 h

- Migration `create_sincronizacoes_table` (D8.1). Enums `OrigemSincronizacao` e
  `StatusSincronizacao`, com `rotulo()`. Model `Sincronizacao` (`RegistraAutoria`,
  morph `sincronizacao`), escopos `emAndamento()` e `concluidas()`, e `ultimaConcluida()`.
- `Conciliacao::disparar(OrigemSincronizacao $origem): Sincronizacao` (D8.2) e a exceção `SincronizacaoEmAndamento`.
- `App\Integrations\Uau\Jobs\ConcluirSincronizacaoJob($id)` (fila `uau`, sem retentativa: só grava).

**Testes** (`tests/Feature/Uau/SincronizacaoTest.php`, simulado + fila `sync`):
- a cadeia completa termina com a sincronização `concluida`, `concluida_em` preenchido e o autor gravado;
- com o UAU recusando a lista de empresas, fica `falhou` com o erro, e obras não são importadas;
- uma segunda chamada com outra em andamento lança `SincronizacaoEmAndamento`;
- uma em andamento há mais de 1 h é marcada interrompida, e a nova segue.

## 8.2 Lista, filtros e detalhe — 2 h

`App\Http\Controllers\Integracoes\IntegracaoController`, padrão da auditoria (`ajaxWithForm`, modal):

| Método | URI | Nome | Permissão |
| --- | --- | --- | --- |
| GET | `/integracoes` | `integracao.index` | `integracao-list` |
| GET | `/integracoes/{id}` | `integracao.show` | `integracao-list` |

- **Filtros:**
  - de/até (padrão: últimos 7 dias, como a auditoria);
  - situação: sucesso, erro sem solução, erro resolvido;
  - operação (`OperacaoUau`);
  - direção.
  A URL aceita os filtros na query (`?situacao=sem-solucao`), para o link do indicador.
- **Colunas:**
  - data com milissegundos;
  - operação (rótulo);
  - objeto ("Empresa · razão social", "Pessoa física · nome", "—");
  - situação (badge);
  - HTTP;
  - duração;
  - tentativa;
  - ações ("Detalhes"; "Reprocessar" quando cabe).
- **Objeto:** `with('entidade')` (carregamento sob demanda barrado). Rótulos num
  mapa por apelido polimórfico, como `Auditoria::OBJETOS`.
- **Detalhe (modal):**
  - rota, correlação, chave, tentativa e erro;
  - `payload` e `resposta` em JSON formatado (D8.6);
  - se a falha foi resolvida, a data da chamada que a resolveu.
- Migration do índice do D8.3.

**Testes** (`tests/Feature/Integracoes/IntegracaoTelaTest.php`):
- cada filtro devolve só as linhas certas: período, operação, direção, sucesso, erro sem solução, erro resolvido;
- uma falha seguida de sucesso da mesma chave aparece como resolvida; de outra chave, não;
- o detalhe mostra o JSON e nunca o texto de um segredo (usa os dados mascarados do simulado);
- as rotas entram em `PermissoesRotasTest`.

## 8.3 Reprocessar — 1 h

- `POST /integracoes/{id}/reprocessar` (`integracao.reprocessar`, `integracao-reenviar`), resposta JSON
  `{success, message}`, como as ações existentes (`requests.js`).
- `App\Actions\Integracoes\ReprocessarFalha::executar(IntegracaoLog): string`
  (a mensagem para a tela). Lança `NaoReprocessavel` com o motivo: já resolvida, sucesso, operação sem importação.
- `requests.js` ganha `postAction(url, pergunta)`, reaproveitado pelo botão da fatia 6.
- `Auditoria::ACOES` ganha `integracao-reprocessada`.

**Testes** (`tests/Feature/Integracoes/ReprocessarFalhaTest.php`, `Queue::fake`):
- uma falha de cada operação despacha o job certo, na fila `uau`, com o documento certo para a pessoa;
- uma falha resolvida, um sucesso e uma autenticação são recusados com 422 e a mensagem;
- o reprocessamento registra a auditoria;
- a rota entra em `PermissoesRotasTest`.

## 8.4 Indicador — 1 h

- `App\Http\View\Composers\IndicadorIntegracao` no `_navbar.blade.php`, no lugar
  reservado pela fatia 1 (D8.5). Só calcula para quem tem `integracao-list`.
- Cache de 60 s (`integracao:indicador`), limpo quando uma sincronização termina ou falha.

**Testes** (`tests/Feature/Integracoes/IndicadorIntegracaoTest.php`):
- sem a permissão, o sino não aparece;
- conta só as falhas sem solução das últimas 24 h (não conta resolvidas nem as mais antigas);
- mostra a última sincronização concluída e "nenhuma" quando não há.

## 8.5 Verificação e documentação — 0,5 h

- No Docker, com o simulado:
  - `falharEm` não serve fora dos testes. Para simular a queda, apontar o
    `FakeUauClient` para uma pasta sem `obras-ativas.json` (dá `RuntimeException`, que não é do UAU).
    Por isso a verificação usa o real desligado: `UAU_FAKE=false` com `UAU_BASE_URL=https://127.0.0.1:9`
    numa execução do tinker, que produz `UauIndisponivel` e a linha de erro;
  - conferir a falha na lista e no indicador, reprocessar com o simulado e ver a falha virar resolvida.
- `docs/desenvolvimento.md`: monitoramento, falha resolvida e reprocessamento.
- `migrate:fresh --seed` no SQLite e num MySQL temporário (há migration nova).

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Agendamento às 05:00 e botão "Sincronizar agora" | Fatia 6 (usa `Conciliacao::disparar()`) |
| Alerta por e-mail de falha (`alerta-integracao`) | E2+, conforme o README |
| Limpeza de `integracao_logs` antigos | E6, com a política de retenção |
| Reprocessar uma carga de planilha que falhou pela tela | E2; até lá, pelo tinker (`docs/desenvolvimento.md`) |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 8.1 a 8.4.
2. Os filtros devolvem só as linhas certas, e uma falha seguida de sucesso aparece como resolvida.
3. Reprocessar despacha o job daquela entidade na fila `uau`.
4. O indicador mostra as falhas sem solução das últimas 24 h e a última sincronização concluída.
5. As rotas estão em `PermissoesRotasTest`.

## Riscos

| Risco | Saída |
| --- | --- |
| `integracao_logs` cresce rápido (uma linha por pessoa por dia) e a subconsulta de "resolvida" fica lenta | Índice do D8.3 e filtro de período sempre aplicado (padrão 7 dias). Medir na homologação; limpeza na E6 |
| "Concluída" antes das consultas de pessoa terminarem confunde | Texto da tela: "empresas e obras atualizadas; consultas de pessoas enviadas para a fila". Pessoas com erro aparecem na lista |
| O `catch` da cadeia é uma closure serializada | Usa só o id da sincronização; testado com a fila `sync` |
