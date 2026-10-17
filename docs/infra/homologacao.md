# Servidor de homologação — roteiro de instalação

Do Ubuntu 24.04 zerado até a ABL PRIME entrar no sistema. Escrito para quem instala
o servidor (fatia 9 da E1). A E6 repete este roteiro em produção, trocando `develop` por `main`.

Cada passo tem o comando e a **conferência**. Só siga para o próximo com a conferência ok.
Os arquivos de configuração estão em [`infra/`](../../infra) e o deploy em [`deploy.sh`](../../deploy.sh).
O que fica a cargo da ABL PRIME aparece como `<...>`.

## 0. Antes de começar

| Item | Quem fornece | Usado no passo |
| --- | --- | --- |
| VPS Ubuntu 24.04, 2 vCPU, 8 GB (Hostinger KVM 2 ou equivalente), acesso root por SSH | ABL PRIME | 1 |
| Domínio de homologação (ex.: `prestadores-homolog.ablprime.com.br`) apontando para o IP da VPS (registro A) | ABL PRIME | 8 |
| Conta no Brevo e acesso ao DNS do domínio do remetente (SPF/DKIM) | ABL PRIME | 13 |
| E-mail do administrador e um e-mail por perfil (RH, Gestor, Financeiro, Colaborador) | ABL PRIME | 11 |
| URL HTTPS da UAU API de homologação, usuário técnico (até 8 caracteres), senha e token de integração | ABL PRIME / Globaltec | 12 (opcional: sem eles, roda com o simulado) |
| Chave de deploy (somente leitura) no repositório do GitHub | Quem administra o repositório | 5 |

## 1. Pacotes

Como root:

```bash
apt update && apt upgrade -y
apt install -y software-properties-common ca-certificates curl git unzip ufw
add-apt-repository -y ppa:ondrej/php          # Ubuntu 24.04 traz o PHP 8.3; o projeto usa 8.4
apt install -y nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring php8.4-xml php8.4-zip \
  php8.4-gd php8.4-curl php8.4-bcmath php8.4-intl php8.4-imagick
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs
```

Composer, conferindo a assinatura do instalador:

```bash
cd /tmp
EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o composer-setup.php
echo "${EXPECTED}  composer-setup.php" | sha384sum -c - && php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

**Conferência:**
- `php -v` mostra 8.4;
- `php -m` lista `redis`, `zip`, `gd`, `intl`;
- `node -v` mostra 22;
- `composer -V` responde.

## 2. Usuário `deploy`

```bash
adduser --disabled-password --gecos "" deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
```

O PHP-FPM roda como `deploy`, o mesmo usuário do Horizon e do cron. Assim os três escrevem
em `storage/` sem conflito de permissão. Em `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
user = deploy
group = deploy
listen.owner = www-data
listen.group = www-data
```

Depois, `systemctl restart php8.4-fpm`. Os limites do PHP (`infra/php/`) entram no passo 5, quando o código estiver no servidor.

**Conferência:** `ps -o user= -C php-fpm8.4 | sort -u` mostra `deploy` (e `root` no processo mestre).

## 3. Firewall e SSH

```bash
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/; s/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl reload ssh
```

**Conferência:**
- `ufw status` mostra só 22, 80 e 443;
- **antes de fechar a sessão root**, abrir outra janela e entrar como `deploy` por chave.

## 4. MySQL e Redis

```bash
mysql_secure_installation
mysql -e "CREATE DATABASE abl_prestadores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER 'abl_prestadores'@'localhost' IDENTIFIED BY '<senha-forte-do-mysql>';
          GRANT ALL ON abl_prestadores.* TO 'abl_prestadores'@'localhost';"
```

Redis só em `127.0.0.1` (padrão do pacote), com senha. Em `/etc/redis/redis.conf`:
`requirepass <senha-do-redis>`; depois `systemctl restart redis-server`.

**Conferência:**
- `mysql -u abl_prestadores -p abl_prestadores -e 'select 1'` funciona;
- `redis-cli -a '<senha-do-redis>' ping` responde `PONG`;
- `ss -lnt` mostra 3306 e 6379 só em 127.0.0.1.

## 5. Código e `.env`

```bash
mkdir -p /var/www/abl-prestadores && chown deploy:deploy /var/www/abl-prestadores
su - deploy
ssh-keygen -t ed25519 -C "deploy@homologacao" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub     # cadastrar como deploy key (somente leitura) no GitHub
git clone -b develop git@github.com:<organizacao>/abl-prestadores.git /var/www/abl-prestadores
cd /var/www/abl-prestadores
cp .env.homologacao.example .env && chmod 600 .env
nano .env                      # preencher os <...>; ver os comentários do arquivo
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate
php artisan storage:link       # fotos de perfil (disco public)
exit                           # de volta ao root
cp /var/www/abl-prestadores/infra/php/99-abl-prestadores.ini /etc/php/8.4/fpm/conf.d/
cp /var/www/abl-prestadores/infra/php/99-abl-prestadores.ini /etc/php/8.4/cli/conf.d/
systemctl restart php8.4-fpm
```

**Conferência:**
- `php -i | grep upload_max_filesize` mostra 5M;
- `php artisan about` mostra `Environment: homologacao` e `Debug Mode: OFF`;
- o MySQL e o Redis aparecem conectados (`php artisan migrate:status` responde, mesmo sem tabelas).

## 6. Nginx

```bash
cp infra/nginx/abl-prestadores.conf /etc/nginx/sites-available/abl-prestadores
sed -i 's/DOMINIO/<dominio-de-homologacao>/' /etc/nginx/sites-available/abl-prestadores
ln -s /etc/nginx/sites-available/abl-prestadores /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
cp infra/logrotate/abl-prestadores /etc/logrotate.d/abl-prestadores
```

**Conferência:** `nginx -t` ok; `curl -I http://<dominio-de-homologacao>` responde (500 é esperado antes do passo 10).

## 7. Supervisor (Horizon)

```bash
cp infra/supervisor/horizon.conf /etc/supervisor/conf.d/abl-prestadores-horizon.conf
supervisorctl reread && supervisorctl update
```

**Conferência:** `supervisorctl status` mostra `abl-prestadores-horizon RUNNING`.

## 8. HTTPS

```bash
certbot --nginx -d <dominio-de-homologacao> --redirect -m <email-do-administrador> --agree-tos -n
```

**Conferência:**
- `https://<dominio-de-homologacao>` abre com cadeado;
- `http://` redireciona para `https://`;
- `systemctl list-timers | grep certbot` mostra a renovação automática.

Sem domínio ainda? Seguir com o IP e HTTP só para a equipe técnica, e não liberar à ABL
PRIME: o cookie de sessão é `secure` (`SESSION_SECURE_COOKIE=true`) e o login não
funciona sem HTTPS. Repetir este passo quando o domínio chegar.

## 9. Cron do scheduler

```bash
cp infra/cron/abl-prestadores /etc/cron.d/abl-prestadores && chmod 644 /etc/cron.d/abl-prestadores
```

**Conferência:** `php artisan schedule:list` mostra `uau:sincronizar` às 05:00. No dia
seguinte, o quadro "Sincronizações" da tela Integrações mostra uma "Agendada".

## 10. Primeiro deploy

Como `deploy`, na pasta da aplicação:

```bash
./deploy.sh
php artisan db:seed --force        # permissões, perfis e o administrador (ADMIN_EMAIL)
```

O seed mostra **uma única vez** a senha do administrador. Guardar e trocar no primeiro acesso.

**Conferência:**
- o login abre com a faixa amarela "Ambiente de homologação";
- o administrador entra;
- `php artisan horizon:status` responde `running`.

## 11. Usuários de homologação

Com `HOMOLOGACAO_USUARIOS` preenchido no `.env` (`php artisan optimize` depois de mudar o `.env`):

```bash
php artisan db:seed --class=HomologacaoSeeder --force
```

A tabela com as senhas aparece uma vez. Enviar a cada pessoa por canal seguro
(nunca por e-mail com a senha no corpo junto do login) e pedir a troca no primeiro acesso.

**Conferência:** cada usuário entra e vê o menu do seu perfil (critério 1 do roteiro de validação).

## 12. UAU

**Com o simulado** (`UAU_FAKE=true`, enquanto não houver credenciais): `php artisan uau:sincronizar` e
conferir em Integrações. Os dados são fictícios; a ABL PRIME valida telas e fluxos, não os dados.

**Com o UAU real** (HTTPS, usuário técnico e token):

1. No `.env`: `UAU_FAKE=false`, `UAU_BASE_URL`, `UAU_LOGIN`, `UAU_SENHA`, `UAU_TOKEN_INTEGRACAO`.
2. `php artisan optimize && php artisan horizon:terminate`: com a config em cache,
   mudança no `.env` só vale depois disso.
3. `php artisan uau:ping` mostra a versão do UAU.
4. `php artisan uau:ping --documento=<cpf de uma pessoa que existe>` e
   `--documento=<cpf que não existe>`. Fecha duas pendências da fatia 4:
   - **D4.5:** a resposta de "não encontrada" casa com `uau.padrao_nao_encontrada`;
   - **D4.8:** o `Authorization` sem "Bearer" funciona.

   Se der 401 na autenticação, testar `UAU_AUTHORIZATION_PREFIXO="Bearer "`.
5. `php artisan uau:sincronizar` e acompanhar em Integrações até "Concluída".

**Conferência:** empresas e obras aparecem nas telas; nenhuma falha sem solução no sino.

## 13. E-mail (recuperação de senha)

1. No Brevo, autenticar o domínio do remetente. O Brevo mostra os registros a criar no DNS:
   - SPF: TXT com `include:spf.brevo.com`, somado ao SPF existente, nunca um segundo registro SPF;
   - DKIM;
   - DMARC (`v=DMARC1; p=none; rua=mailto:<email>` no começo).
2. `.env`: `MAIL_*` conforme o arquivo de exemplo. Depois, `php artisan optimize`.
3. Na tela de login, "Esqueci minha senha" com um usuário de homologação.

**Conferência:**
- o e-mail chega na **caixa de entrada** (não no spam) do Gmail e do Outlook;
- nos cabeçalhos aparecem `spf=pass` e `dkim=pass`;
- o link troca a senha.

Fecha a pendência 1.4 da fatia 1.

## 14. Checklist final

- [ ] `https://` com cadeado, `http://` redireciona
- [ ] Faixa "Ambiente de homologação" visível
- [ ] `supervisorctl status`: Horizon `RUNNING`; `horizon:status`: `running`
- [ ] `schedule:list` com `uau:sincronizar` às 05:00; cron instalado
- [ ] Administrador e um usuário por perfil criados; senhas entregues por canal seguro
- [ ] Sincronização concluída (simulado ou UAU real) e sem falha no sino
- [ ] Recuperação de senha chega na caixa de entrada
- [ ] `APP_DEBUG=false`; `.env` com permissão 600; root sem login por SSH
- [ ] Roteiro de validação entregue à ABL PRIME: [`docs/homologacao/roteiro-validacao.md`](../homologacao/roteiro-validacao.md)

## Deploys seguintes

Antes, na sua máquina: `vendor/bin/phpunit` verde na `develop`. No servidor, como `deploy`:

```bash
cd /var/www/abl-prestadores && ./deploy.sh
```

O site fica em manutenção durante o deploy e volta sozinho, mesmo se um passo falhar.
Se o `git pull` recusar (`--ff-only`), alguém alterou arquivos no servidor: `git status`
mostra o quê. Nunca edite código direto no servidor.

## Problemas comuns

| Sintoma | Causa provável | Saída |
| --- | --- | --- |
| Mudei o `.env` e nada mudou | Config em cache | `php artisan optimize && php artisan horizon:terminate` |
| Fila parada, nada sincroniza | Horizon fora | `supervisorctl status`; `tail storage/logs/horizon.log` |
| 419 ao enviar formulário | Sessão expirada ou acesso por `http://` | Entrar de novo por `https://` |
| Login não mantém a sessão | Acesso sem HTTPS com `SESSION_SECURE_COOKIE=true` | Passo 8 |
| "Permission denied" em `storage/` | Arquivo criado pelo root | `chown -R deploy:deploy storage bootstrap/cache` |
| Sincronização "Em andamento" há horas | Worker caiu no meio | Ela é dada como interrompida depois de 1 h; conferir o Horizon e pedir outra |
