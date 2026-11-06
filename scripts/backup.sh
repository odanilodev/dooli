#!/usr/bin/env bash
# Backup de producao: dump do MySQL + arquivos enviados (volume dooli_prod_storage).
# Nao para nenhum container. Redis nao entra (so cache, sessao e fila).
#
# Uso manual:   bash /opt/dooli/scripts/backup.sh
# Cron diario:  0 3 * * * bash /opt/dooli/scripts/backup.sh >> /var/log/dooli-backup.log 2>&1
#
# Variaveis opcionais:
#   BACKUP_DIR      destino (padrao /opt/dooli-backups, fora da pasta de deploy)
#   RETENTION_DAYS  dias de retencao (padrao 14)

set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/opt/dooli-backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
DB_CONTAINER="${DB_CONTAINER:-dooli_db}"
STORAGE_VOLUME="${STORAGE_VOLUME:-dooli_prod_storage}"
APP_IMAGE="${APP_IMAGE:-dooli-app:production}"

TS="$(date +%Y%m%d_%H%M%S)"
OUT="${BACKUP_DIR}/${TS}"

mkdir -p "${OUT}"
chmod 700 "${BACKUP_DIR}"

echo "[$(date '+%F %T')] Backup iniciado -> ${OUT}"

# 1) Banco. A senha vem do ambiente do proprio container (nao aparece na lista de processos do host).
docker exec "${DB_CONTAINER}" sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --routines --triggers --default-character-set=utf8mb4 --databases "$MYSQL_DATABASE"' \
    | gzip > "${OUT}/db.sql.gz"

gzip -t "${OUT}/db.sql.gz"
[ "$(stat -c %s "${OUT}/db.sql.gz")" -gt 1024 ] || { echo "ERRO: dump do banco vazio ou pequeno demais"; exit 1; }

# 2) Arquivos enviados (uploads, anexos, avatares). Usa a imagem do app para nao baixar nada.
docker run --rm --entrypoint tar -v "${STORAGE_VOLUME}":/data:ro "${APP_IMAGE}" \
    czf - -C /data app > "${OUT}/storage.tar.gz"

gzip -t "${OUT}/storage.tar.gz"

chmod 600 "${OUT}"/*

# 3) Retencao
find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -mtime +"${RETENTION_DAYS}" -exec rm -rf {} +

echo "[$(date '+%F %T')] Backup concluido: $(du -sh "${OUT}" | cut -f1) em ${OUT}"
