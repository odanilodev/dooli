#!/usr/bin/env bash
# Deploy de produção: build + update dos containers + migrations + caches + health check.
# Chamado pelo GitHub Actions (SKIP_GIT_PULL=1, código já extraído) ou manualmente no servidor.
# Uso: bash deploy-prod.sh
#
# Primeira implantação (volume do banco ainda não existe):
#   SKIP_GIT_PULL=1 ALLOW_NEW_DB_VOLUME=1 bash deploy-prod.sh

set -euo pipefail

cd "$(dirname "$0")"

COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE=".env.production"
PROJECT_NAME="dooli"
DB_VOLUME="${PROJECT_NAME}_prod_dbdata"
WEB_PORT="$(grep -E '^WEB_PORT=' "${ENV_FILE}" 2>/dev/null | cut -d= -f2 | tr -d '"' || true)"
HEALTH_URL="http://127.0.0.1:${WEB_PORT:-9100}/up"
HEALTH_MAX_ATTEMPTS="${HEALTH_MAX_ATTEMPTS:-40}"
HEALTH_SLEEP_SECONDS="${HEALTH_SLEEP_SECONDS:-3}"

run_compose() {
    docker compose -p "${PROJECT_NAME}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" "$@"
}

echo
echo "========================================"
echo "PRODUCTION DEPLOY - dooli"
echo "========================================"

if [ ! -f "${ENV_FILE}" ]; then
    echo "ERRO: ${ENV_FILE} nao encontrado em $(pwd). Crie a partir de .env.production.example."
    exit 1
fi

if grep -v '^[[:space:]]*#' "${ENV_FILE}" | grep -q 'CHANGE_ME'; then
    echo "ERRO: ${ENV_FILE} ainda tem CHANGE_ME nestas linhas:"
    grep -n 'CHANGE_ME' "${ENV_FILE}" | grep -v '^[0-9]*:[[:space:]]*#' | sed 's/=.*/=.../'
    exit 1
fi

if ! grep -Eq '^APP_KEY=base64:.+' "${ENV_FILE}"; then
    echo "ERRO: APP_KEY ausente ou fora do formato (APP_KEY=base64:...) em ${ENV_FILE}."
    exit 1
fi

echo
echo "[1/7] Atualizacao do codigo"
if [ "${SKIP_GIT_PULL:-0}" = "1" ] || [ ! -d .git ]; then
    echo "Sem git pull -> codigo ja foi enviado pelo CI (tarball)"
else
    git fetch --all --prune
    git reset --hard origin/main
fi

echo
echo "[2/7] Trava de seguranca do volume do banco"
if [ "${ALLOW_NEW_DB_VOLUME:-0}" != "1" ] && ! docker volume ls --format '{{.Name}}' | grep -Fxq "${DB_VOLUME}"; then
    echo "O volume '${DB_VOLUME}' nao existe. Subir agora criaria um banco NOVO e vazio."
    echo "Se for a primeira implantacao: ALLOW_NEW_DB_VOLUME=1 bash deploy-prod.sh"
    exit 1
fi

echo
echo "[3/7] Build das imagens"
run_compose build

echo
echo "[4/7] Subindo containers"
run_compose up -d --remove-orphans

echo
echo "[5/7] Aguardando health check (${HEALTH_URL})"
attempt=1
until curl -fsS "${HEALTH_URL}" >/dev/null 2>&1; do
    if [ "${attempt}" -ge "${HEALTH_MAX_ATTEMPTS}" ]; then
        echo "Health check falhou apos ${HEALTH_MAX_ATTEMPTS} tentativas. Ultimos logs:"
        run_compose logs --tail=100 app web || true
        exit 1
    fi
    echo "Aguardando... ${attempt}/${HEALTH_MAX_ATTEMPTS}"
    attempt=$((attempt + 1))
    sleep "${HEALTH_SLEEP_SECONDS}"
done
echo "Health OK"

echo
echo "[6/7] Migrations e caches"
run_compose exec -T app php artisan migrate --force
run_compose exec -T app php artisan storage:link 2>/dev/null || true
run_compose exec -T app php artisan config:cache
run_compose exec -T app php artisan view:cache
# Reinicia os workers para carregarem o codigo novo
run_compose exec -T queue php artisan queue:restart || true

echo
echo "[7/7] Status"
run_compose ps
docker image prune -f >/dev/null 2>&1 || true

echo
echo "========================================"
echo "DEPLOY CONCLUIDO"
echo "========================================"
