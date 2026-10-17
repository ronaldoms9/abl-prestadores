# E1 · Fatia 9 — Homologação: VPS + `deploy.sh` · 10 h · semana 3

Detalha a fatia 9 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md). É a última fatia da E1.
Não depende de código das outras, mas fecha pendências delas: o e-mail real da 1.4,
o `HomologacaoSeeder` da 2, o cron do scheduler da 6 e o roteiro de homologação da 6 e da 7.

A fatia tem duas partes, porque a VPS ainda não foi entregue:

| Parte | O que é | Horas | Quando |
| --- | --- | --- | --- |
| A — no repositório | Ambiente `homologacao`, seeders seguros, `deploy.sh`, arquivos de configuração do servidor, roteiros | 5 | Agora |
| B — no servidor | Executar o roteiro na VPS, HTTPS, DNS, primeira carga, e-mail real | 5 | Quando a ABL PRIME entregar a VPS e o domínio |

Ao fim da parte A, montar o servidor é seguir o roteiro (`docs/infra/homologacao.md`),
sem decisão pendente. Ao fim da parte B, a ABL PRIME entra na homologação com um
usuário por perfil e valida os 6 critérios de aceite da E1 com o roteiro de validação.

Branch `e1/fatia-9-homologacao`, a partir de `e1/fatia-7-consultas`. Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 9.1 | Ambiente `homologacao` no código | 1 |
| 9.2 | Seeders seguros: administrador e um usuário por perfil | 1 |
| 9.3 | `deploy.sh` e arquivos de configuração do servidor | 1,5 |
| 9.4 | Roteiro de instalação e roteiro de validação com a ABL PRIME | 1,5 |
| 9.5 | **(parte B)** Servidor, HTTPS, DNS, deploy, carga e e-mail real | 5 |
| | **Total** | **10** |

---

## Decisões

**D9.1 — `APP_ENV=homologacao`, não `production`.**
- Um nome próprio deixa claro em logs e no Horizon onde se está. Também permite:
  - a faixa "Ambiente de homologação" no topo, para ninguém da ABL PRIME confundir com produção;
  - o `HomologacaoSeeder` recusar produção.
- Mantém o comportamento de produção onde importa: `APP_DEBUG=false`, caches de config/rota/view, HTTPS obrigatório para o UAU.
- **Carregamento sob demanda de relações** (barrado fora de produção): quebra em `local`
  e `testing`; em `homologacao`, só registra no log. A ABL PRIME não vê erro por isso,
  e o log mostra o que corrigir.
- O Horizon ganha o ambiente `homologacao`, com os mesmos supervisores de produção.
  Sem ele, o Horizon não sobe nenhum worker.

**D9.2 — Nenhuma senha conhecida fora do ambiente local.**
- **Hoje:** o `UserCreateSeeder` cria `ronaldo@rmsti.com.br` com a senha fixa `mudar@123`.
- **Fora de `local`:**
  - o e-mail do administrador vem de `ADMIN_EMAIL` (obrigatório);
  - a senha é aleatória, mostrada uma única vez no terminal;
  - o usuário troca a senha pela tela de perfil ou pelo "Esqueci minha senha".
- **Em `local`:** continua como está, para não atrapalhar o desenvolvimento.

**D9.3 — `HomologacaoSeeder`: um usuário por perfil.**
- Cria um usuário ativo para cada perfil do `PerfilSeeder`, exceto Administrador, que é o do D9.2.
- **E-mails:** vêm de `HOMOLOGACAO_USUARIOS` (`rh:maria@abl.com.br,gestor:...`). Perfil
  sem e-mail na variável usa `homologacao+<perfil>@<dominio de ADMIN_EMAIL>`.
- **Senhas:** aleatórias (12 caracteres, letras e números, dentro da Q21), mostradas uma única vez numa tabela no terminal.
- **Idempotente:** usuário que já existe não é alterado e não tem a senha trocada.
- **Recusa:** em `production`, lança exceção. Não entra no `DatabaseSeeder`; roda com
  `php artisan db:seed --class=HomologacaoSeeder`.

**D9.4 — `deploy.sh` com janela de manutenção e falha rápida.**
- `set -euo pipefail`.
- `php artisan down` com um `trap` que sempre roda `php artisan up`, mesmo se um passo falhar.
- **Branch por parâmetro:** `develop` por padrão, como na homologação (D6 do README); a E6 usa `main`.
- `git pull --ff-only`: se o servidor tiver commit local, o deploy para em vez de fazer merge.
- **Depois do `migrate --force`:**
  - `php artisan optimize` (config, rotas, views, eventos);
  - `horizon:terminate`: o Supervisor sobe o Horizon de novo, com o código novo.
- **Sem passo de testes:** os testes rodam antes, na máquina de quem faz o deploy (README: não há CI).

**D9.5 — Configuração do servidor versionada em `infra/`.** Nginx, Supervisor (Horizon), cron
(scheduler), PHP (limites e OPcache) e logrotate ficam como arquivos no repositório.
O roteiro manda copiá-los. Assim a E6 repete em produção sem reescrever nada.
- Limites: `client_max_body_size 8M` (planilha de 2 MB + foto de perfil); `upload_max_filesize 5M`; `memory_limit 256M`.
- **Supervisor:** `stopwaitsecs=3600`. O Horizon espera o job mais longo (900 s na fila `uau`) antes de parar.
- **Logs:** `LOG_CHANNEL=daily`, 14 dias; o logrotate cuida dos logs do Nginx.

**D9.6 — Dois roteiros, dois públicos.**
- `docs/infra/homologacao.md`, para quem instala: do Ubuntu 24.04 zerado até o primeiro deploy.
  Cada passo tem o comando e a conferência.
- `docs/homologacao/roteiro-validacao.md`, para a ABL PRIME: os 6 critérios de aceite da E1, cada um com
  - os passos na tela;
  - o resultado esperado;
  - um espaço para "ok / observação".
  O roteiro também diz o que está fora da E1:
  - sem webhook do UAU;
  - obra no lugar de centro de custo;
  - busca avulsa só na E2;
  - vínculo sugerido sem aceite;
  - status da obra como código.

---

## 9.1 Ambiente `homologacao` — 1 h

- `AppServiceProvider`:
  - `preventLazyLoading` fica ligado fora de produção;
  - `handleLazyLoadingViolationUsing` registra no log quando o ambiente não é `local` nem `testing` (D9.1).
- `config/horizon.php`: ambiente `homologacao` igual ao de `production`.
- Faixa no layout (`layouts/app.blade.php` e `layouts/auth.blade.php`) quando `app()->environment('homologacao')`.
- `.env.homologacao.example`: todas as variáveis do servidor, comentadas, sem segredos.

**Testes** (`tests/Feature/AmbienteHomologacaoTest.php`):
- a faixa aparece em `homologacao` e não em `local`;
- em `homologacao`, um carregamento sob demanda grava aviso no log e não lança exceção;
- o Horizon tem supervisores para `homologacao`.

## 9.2 Seeders seguros — 1 h

- `UserCreateSeeder` (D9.2) e `HomologacaoSeeder` (D9.3).
- **Testes** (`tests/Feature/SeedersTest.php`, que já existe, e `HomologacaoSeederTest.php`):
  - fora de `local`, sem `ADMIN_EMAIL` o seeder falha com mensagem clara;
  - com `ADMIN_EMAIL`, a senha não é `mudar@123`;
  - um usuário por perfil, menos o Administrador; e-mails da variável e o padrão;
  - rodar duas vezes não troca a senha;
  - recusa `production`;
  - as senhas geradas passam na política (Q21).

## 9.3 `deploy.sh` e `infra/` — 1,5 h

- `deploy.sh` na raiz (D9.4), executável.
- Em `infra/` (D9.5):
  - `nginx/abl-prestadores.conf`;
  - `supervisor/horizon.conf`;
  - `cron/abl-prestadores`;
  - `php/99-abl-prestadores.ini`;
  - `logrotate/abl-prestadores`.
- **Conferência local:**
  - `bash -n deploy.sh`;
  - `php artisan optimize` e `optimize:clear` no Docker, para confirmar que rotas e config são cacheáveis
    (closure em rota ou `env()` fora de `config/` quebrariam o cache).
  - `nginx -t` com o arquivo num container `nginx` descartável.

## 9.4 Roteiros — 1,5 h

- `docs/infra/homologacao.md` (D9.6), em seções:
  - pré-requisitos (o que pedir à ABL PRIME);
  - pacotes;
  - usuário `deploy`;
  - firewall;
  - MySQL e Redis;
  - código e `.env`;
  - Nginx e HTTPS;
  - Supervisor;
  - cron;
  - primeiro deploy e seeders;
  - primeira sincronização e carga;
  - e-mail (SPF/DKIM no Brevo);
  - checklist final.
- `docs/homologacao/roteiro-validacao.md` (D9.6).
- `docs/desenvolvimento.md` aponta para os dois.

## 9.5 Parte B, no servidor — 5 h (quando houver VPS)

1. Seguir `docs/infra/homologacao.md` do início ao fim.
2. `HomologacaoSeeder` com os e-mails da ABL PRIME e envio das senhas por canal seguro.
3. **UAU:** com HTTPS, usuário técnico e token, `UAU_FAKE=false`. Depois:
   - `uau:ping --documento=...` para fechar D4.5 e D4.8 da fatia 4;
   - `uau:sincronizar`.

   Sem eles, fica `UAU_FAKE=true`, e a validação com o UAU real fica para depois.
4. Carga da planilha real dos prestadores (5b).
5. Recuperação de senha com o e-mail real, conferindo SPF/DKIM e que não cai no spam (pendência da 1.4).
6. Reunião de homologação com o roteiro de validação.

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Produção, backup automatizado e monitoramento de servidor | E6 |
| CI (testes automáticos no push) | Fora do escopo (README: sem CI) |
| Sentry | E6 (D3 do README) |

## Pronto quando

**Parte A:**
1. `vendor/bin/phpunit` verde, com os testes de 9.1 e 9.2.
2. Nenhum seeder deixa senha conhecida fora do ambiente local.
3. `deploy.sh` passa no `bash -n`. `php artisan optimize` funciona.
4. Os dois roteiros estão completos, sem "a definir" além dos dados da ABL PRIME (domínio, IP, e-mails, credenciais do UAU).

**Parte B:** a ABL PRIME entra com um usuário por perfil e valida os 6 critérios do roteiro.

## Notas da implementação da parte A (2026-10-17)

- **Carregamento sob demanda:** a decisão (quebrar ou só registrar) é tomada no momento do
  carregamento, e não no boot. Fica testável e mantém a regra do Laravel: model recém-criado não conta.
- **Configuração:** `ADMIN_EMAIL` e `HOMOLOGACAO_USUARIOS` entram por `config/implantacao.php`,
  porque `env()` fora de `config/` quebraria o `php artisan optimize`.
- **Servidor:** uma pasta só (`/var/www/abl-prestadores`, `.env` ao lado), sem pastas de
  release. Na homologação basta a janela de manutenção do `deploy.sh`.
- **PHP-FPM como `deploy`:** o mesmo usuário do Horizon e do cron, para os três escreverem em `storage/` sem conflito.
- **Verificado:**
  - `bash -n deploy.sh`;
  - `nginx -t` com o arquivo do site num container `nginx:1.27`;
  - `php artisan optimize` no Docker (config, eventos, rotas e views cacheáveis), seguido de `optimize:clear`.
- **Parte B:** pendente, aguarda a VPS, o domínio e as credenciais do UAU.

## Riscos

| Risco | Saída |
| --- | --- |
| VPS ou domínio atrasam | A parte A fica pronta. A homologação pode começar com o IP e um certificado autoassinado, sem e-mail real, e o roteiro registra a pendência |
| Credenciais do UAU não chegam | Homologar com `UAU_FAKE=true` (decisão dos próximos passos); ligar o UAU real é só `.env` |
| Senhas mostradas no terminal ficam no histórico do SSH | A tabela sai na saída do comando, não em arquivo. O roteiro manda trocar no primeiro acesso |
| `php artisan optimize` falha no servidor por `env()` fora de `config/` | Conferido no Docker na 9.3 |
