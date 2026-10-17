# Como rodar

## Subir o ambiente

```bash
cp .env.example .env
php artisan key:generate

docker compose up -d
```

Sobem `app`, `mysql`, `redis` e `horizon`.

**Redis não publica porta.** Só existe dentro da rede `sail`.

## Fila

Fila `default` no Redis. O Horizon roda em um container próprio; o painel fica
em `/horizon`.

## Testes

```bash
vendor/bin/phpunit
```

Os testes usam **SQLite em memória**, então rodam sem Docker e sem MySQL.

## Modelo de dados

- **Estrutura de apoio:**
  - Casts em `app/Casts/` (`SomenteDigitos`, `AsEndereco`).
  - Value objects e helpers em `app/Support/` (`Documento`, `Endereco`).
  - Enums com rótulos amigáveis em `app/Enums/` (`DirecaoIntegracao`, `StatusIntegracao`, `StatusImportacao`, `ResultadoImportacao`).
  - Morph map explícito configurado em `App\Providers\AppServiceProvider::boot()` para tabelas polimórficas (`integracao_logs`, `activity_log`).
  - Prevenção de lazy loading ativa fora de produção (`Model::preventLazyLoading(! app()->isProduction())`).

- **Regra D3.1 (`ativo` × `deleted_at`):**
  - `ativo`: espelha o status no UAU; a importação liga e desliga sem apagar registros.
  - `deleted_at` (auditoria): reservado para exclusão lógica de cadastros feitos por engano na interface.
  - Os índices únicos (`cpf`, `cnpj`, `uau_codigo`) abrangem também registros excluídos logicamente; a busca na importação usa `withTrashed()` para restaurar e atualizar registros excluídos em vez de violar unicidade.

## Integração UAU

Código em `app/Integrations/Uau/`. Tudo passa pela interface `UauGateway`.

- **Simulado × real:** `UAU_FAKE=true` usa o `FakeUauClient`, que lê os dados
  fictícios de `resources/fixtures/uau/` (ver o README da pasta). `UAU_FAKE=false`
  usa o `UauClient`, que exige `UAU_BASE_URL` em HTTPS, `UAU_LOGIN` com até 8
  caracteres, `UAU_SENHA` e `UAU_TOKEN_INTEGRACAO`.
- **Validar no servidor:** `php artisan uau:ping` autentica e mostra a versão do
  UAU. `php artisan uau:ping --documento=<cpf ou cnpj>` testa a consulta de
  pessoa, sem mostrar dados pessoais.
- **Log:** toda chamada, inclusive a autenticação e as do simulado (rota
  `fake:...`), vira uma linha em `integracao_logs`. Senha, token, login e senha do
  portal do UAU e dados bancários são gravados como `[oculto]`
  (`config('uau.campos_ocultos')`). Cada chamada leva um `X-Correlation-ID`,
  gravado em `correlacao`, para localizar o caso junto ao suporte da Globaltec.
- **Erros:** `UauIndisponivel` (rede, timeout, 5xx, 429) deve ser tentada de novo
  pelo job; `UauRejeitou` (4xx, resposta fora do formato) não. Pessoa não
  encontrada é `null`, não erro.
- **Nunca chame o UAU dentro de `DB::transaction`:** o rollback apagaria a linha
  de log da falha.
- **Contexto:** num job, use `$uau->comContexto(entidade: $model, tentativa: $this->attempts())`
  para ligar a chamada à entidade no log. O cliente é singleton e não guarda contexto.
- **Fila `uau`:** os jobs de importação rodam na fila `uau`, processados por um
  supervisor dedicado no Horizon (`supervisor-uau`, 1 processo, timeout de 900 s).
  A execução em série garante que o servidor do UAU não seja sobrecarregado e
  elimina riscos de concorrência/corrida de token.
- **Conciliação à mão:** para rodar a sincronização completa (empresas, obras e pessoas locais):
  ```bash
  php artisan tinker --execute="App\Integrations\Uau\Conciliacao::cadeia()->dispatch();"
  ```
  A cadeia executa `ImportarEmpresasJob`, `ImportarObrasJob` e `ConciliarPessoasJob`. Se
  um elo falhar, os seguintes são interrompidos para evitar dados inconsistentes.
- **Trava da lista vazia e suspeita:** se o UAU retornar uma lista vazia enquanto houver
  registros ativos no banco, ou uma queda que desativaria mais da metade dos ativos
  (`config('uau.limite_inativacao') = 0.5`), a importação lança `ImportacaoSuspensa`
  e não altera nada. Para desativações legítimas em massa, ajuste `UAU_LIMITE_INATIVACAO=1.0`
  no `.env` temporariamente.
- **Monitoramento** (`Integrações`, permissão `integracao-list`): cada linha de
  `integracao_logs`, com filtros. Uma falha fica **resolvida** quando uma chamada
  posterior da mesma operação e da mesma chave deu certo; só as sem solução contam
  no sino do topo (últimas 24 h, cache de 60 s). **Reprocessar** (`integracao-reenviar`)
  despacha de novo o job da importação (empresas, obras ou a pessoa). Falha de
  autenticação não se reprocessa: a nova tentativa do job autentica de novo e a resolve.
  No simulado não há autenticação; uma falha de `autenticar` criada à mão fica sem solução.
- **Sincronizações:** `Conciliacao::disparar(OrigemSincronizacao::Manual)` registra a
  execução em `sincronizacoes` e despacha a cadeia; só uma por vez (uma em andamento há
  mais de 1 h é dada como interrompida). `Conciliacao::cadeia()->dispatch()` continua
  rodando a cadeia sem registro, só para depuração.
- **Agendamento:** `php artisan uau:sincronizar` dispara a conciliação completa e é o que o
  scheduler roda todo dia às `UAU_SINCRONIZACAO_HORARIO` (05:00, fuso `APP_TIMEZONE`). No
  Docker, o serviço `scheduler` roda `schedule:work`; no servidor, o cron chama
  `schedule:run` a cada minuto (fatia 9). Para testar sem esperar:
  `php artisan schedule:test --name=uau:sincronizar`. O botão "Sincronizar agora" fica em
  Integrações (`integracao-sincronizar`); com uma em andamento, recusa com aviso.
- **Carga por planilha** (`Pessoas › Carregar planilha`, permissão `pessoa-importar`):
  colunas `cpf` e `cnpj`, até `UAU_PLANILHA_MAX_LINHAS` linhas (2000). Cabeçalho errado
  recusa o arquivo; dígito verificador errado marca a linha sem consultar o UAU. As
  consultas rodam na fila `uau`, em lotes de `UAU_PLANILHA_LOTE` (50). O arquivo fica em
  `storage/app/importacoes-pessoas/`. Com o simulado, `52998224725` + `27543198000106`
  dão importada e vínculo sugerido; `74620153826` inativa; `11144477735` não encontrada.
  Uma carga que ficou `falhou` retoma das linhas pendentes:
  ```bash
  php artisan tinker --execute="App\Models\ImportacaoPessoa::whereKey(<id>)->update(['status' => 'processando']); App\Integrations\Uau\Jobs\ProcessarImportacaoPessoasJob::dispatch(<id>);"
  ```

