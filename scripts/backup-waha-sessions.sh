#!/usr/bin/env bash
# Backup do volume waha-sessions.
#
# Perder esse volume desconecta todos os clientes ao mesmo tempo e exige QR novo
# de cada um. Rode no HOST (o container da aplicação não tem docker CLI):
#
#   0 3 * * * /caminho/do/projeto/scripts/backup-waha-sessions.sh >> /var/log/waha-backup.log 2>&1
set -euo pipefail

DESTINO="${WAHA_BACKUP_DIR:-$(cd "$(dirname "$0")/.." && pwd)/storage/backups/waha}"
RETENCAO_DIAS="${WAHA_BACKUP_RETENCAO_DIAS:-14}"
PROJETO="$(cd "$(dirname "$0")/.." && basename "$(pwd)")"
VOLUME="${PROJETO}_waha-sessions"
CARIMBO="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$DESTINO"

docker run --rm \
    -v "${VOLUME}:/origem:ro" \
    -v "${DESTINO}:/destino" \
    alpine:3 \
    tar czf "/destino/waha-sessions-${CARIMBO}.tar.gz" -C /origem .

find "$DESTINO" -name 'waha-sessions-*.tar.gz' -mtime "+${RETENCAO_DIAS}" -delete

echo "[$(date -Iseconds)] backup de ${VOLUME} em ${DESTINO}/waha-sessions-${CARIMBO}.tar.gz"
