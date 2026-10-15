# Como rodar

## Subir o ambiente

```bash
cp .env.example .env
php artisan key:generate

# gere as duas chaves do WAHA antes de subir (o compose as lê do .env)
echo "WAHA_API_KEY=$(openssl rand -hex 24)" >> .env
echo "WAHA_HMAC_KEY=$(openssl rand -hex 32)" >> .env

docker compose up -d
```

Sobem `app`, `mysql`, `redis`, `horizon` e `waha`.

**Redis e WAHA não publicam porta.** Só existem dentro da rede `sail`. Publicar
a porta do WAHA é entregar a API do WhatsApp de todos os clientes para a
internet. Para falar com o WAHA a partir da sua máquina, use um container na
mesma rede:

```bash
docker run --rm --network waha-server_sail curlimages/curl \
  -s -H "X-Api-Key: $(grep '^WAHA_API_KEY=' .env | cut -d= -f2)" \
  http://waha:3000/api/version
```

## Fila

Duas filas, `whatsapp` (prioritária) e `default`. Resposta a cliente final não
espera atrás de envio de e-mail. O Horizon roda em um container próprio; o
painel fica em `/horizon`.

## Testes

```bash
vendor/bin/phpunit
```

Os testes usam **SQLite em memória** e o `FakeWhatsAppProvider` (`phpunit.xml`
fixa `WAHA_DRIVER=fake`), então rodam sem Docker, sem MySQL, sem WhatsApp e sem
gastar token. Chamadas à OpenRouter são interceptadas com `Http::fake()`.

As esperas de "digitando..." ficam em zero durante os testes
(`WAHA_DIGITANDO_MIN/MAX`), senão cada teste de conversa levaria 2 a 4 segundos.

Falhas pré-existentes: `tests/Feature/Auth/*` e `tests/Feature/ExampleTest.php`
são testes do Breeze que vieram no boilerplate e apontam para rotas que este
projeto não carrega (`routes/auth.php` não é incluído, e `/` é do
`HomeController`). Já falhavam antes deste trabalho.

## Backup do volume de sessões

Perder `waha-sessions` desconecta todos os clientes ao mesmo tempo e exige QR
novo de cada um. O backup roda no host, não no container:

```cron
0 3 * * * /caminho/do/projeto/scripts/backup-waha-sessions.sh >> /var/log/waha-backup.log 2>&1
```

## Expurgo

`conversas:expurgar` roda todo dia às 03:30 e apaga conversas e mensagens com
`expurgo_em` vencido (90 dias). `metrics_daily` sobrevive: métrica agregada não
é dado pessoal.

```bash
php artisan conversas:expurgar --dry-run
```
