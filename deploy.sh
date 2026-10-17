#!/usr/bin/env bash
#
# Deploy da Gestão de Prestadores (fatia 9, D9.4). Rodado à mão no servidor,
# como o usuário deploy, dentro da pasta da aplicação:
#
#     ./deploy.sh            # branch develop (homologação)
#     ./deploy.sh main       # branch main (produção, E6)
#
# Os testes rodam antes, na máquina de quem faz o deploy (vendor/bin/phpunit);
# não há CI. Roteiro do servidor: docs/infra/homologacao.md
set -euo pipefail

BRANCH="${1:-develop}"
cd "$(dirname "$0")"

passo() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

if [[ ! -f .env ]]; then
    echo "Falta o .env nesta pasta. Veja docs/infra/homologacao.md." >&2
    exit 1
fi

passo "Modo manutenção"
php artisan down --retry=60 --refresh=15
# Volta ao ar mesmo se um passo falhar: a versão anterior continua servindo.
trap 'passo "Saindo do modo manutenção"; php artisan up' EXIT

passo "Código: ${BRANCH}"
git fetch --prune origin
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

passo "Dependências PHP"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

passo "Front-end"
npm ci --no-audit --no-fund
npm run production

passo "Banco"
php artisan migrate --force

passo "Caches (config, rotas, views, eventos)"
php artisan optimize

passo "Reiniciando o Horizon (o Supervisor sobe de novo com o código novo)"
php artisan horizon:terminate

passo "Pronto: $(git log -1 --format='%h %s')"
