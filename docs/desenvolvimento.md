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

