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
