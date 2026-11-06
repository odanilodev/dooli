#!/usr/bin/env bash
# Restaura um backup gerado por scripts/backup.sh.
# Uso: bash /opt/dooli/scripts/restore.sh /opt/dooli-backups/20260101_030000
#
# SOBRESCREVE o banco e os arquivos enviados atuais. Pede confirmacao.

set -euo pipefail

DIR="${1:?Informe a pasta do backup, ex: /opt/dooli-backups/20260101_030000}"
DB_CONTAINER="${DB_CONTAINER:-dooli_db}"
STORAGE_VOLUME="${STORAGE_VOLUME:-dooli_prod_storage}"
APP_IMAGE="${APP_IMAGE:-dooli-app:production}"
APP_DIR="${APP_DIR:-/opt/dooli}"

[ -f "${DIR}/db.sql.gz" ] || { echo "ERRO: ${DIR}/db.sql.gz nao encontrado"; exit 1; }

compose() {
    docker compose -p dooli -f "${APP_DIR}/docker-compose.production.yml" --env-file "${APP_DIR}/.env.production" "$@"
}

echo "Backup: ${DIR}"
read -r -p "Isso SOBRESCREVE o banco e os arquivos atuais. Digite SIM para continuar: " OK
[ "${OK}" = "SIM" ] || { echo "Cancelado."; exit 1; }

echo "Parando app, queue, scheduler e web..."
compose stop web queue scheduler app

echo "Restaurando banco..."
gunzip -c "${DIR}/db.sql.gz" | docker exec -i "${DB_CONTAINER}" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot'

if [ -f "${DIR}/storage.tar.gz" ]; then
    echo "Restaurando arquivos enviados..."
    docker run --rm -i --entrypoint tar -v "${STORAGE_VOLUME}":/data "${APP_IMAGE}" \
        xzf - -C /data < "${DIR}/storage.tar.gz"
fi

echo "Subindo os containers..."
compose start app queue scheduler web

echo "Restauracao concluida."
