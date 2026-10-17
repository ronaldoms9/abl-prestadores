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
