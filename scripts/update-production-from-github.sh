#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="${1:-/dados/sistemas/rvscope}"
REMOTE_NAME="${REMOTE_NAME:-origin}"
BRANCH_NAME="${BRANCH_NAME:-main}"
SERVICE_NAME="${SERVICE_NAME:-app}"
WAIT_SECONDS="${WAIT_SECONDS:-90}"
BASE_COMPOSE_FILE="docker-compose.yaml"
PRODUCTION_COMPOSE_FILE="docker-compose.production-local.yaml"

fail() {
    echo "ERRO: $*" >&2
    exit 1
}

command -v git >/dev/null 2>&1 || fail "git não encontrado."
command -v docker >/dev/null 2>&1 || fail "docker não encontrado."
docker compose version >/dev/null 2>&1 || fail "docker compose não está disponível."

[[ -d "${PROJECT_DIR}/.git" ]] || fail "${PROJECT_DIR} não é um repositório Git."
cd "${PROJECT_DIR}"

[[ -f "${BASE_COMPOSE_FILE}" ]] || fail "Arquivo ${BASE_COMPOSE_FILE} não encontrado."
[[ -f "${PRODUCTION_COMPOSE_FILE}" ]] || fail "Arquivo ${PRODUCTION_COMPOSE_FILE} não encontrado."

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "O repositório possui alterações locais. Revise-as antes da atualização."
fi

PREVIOUS_COMMIT="$(git rev-parse --short=12 HEAD)"
if docker container inspect rvscope_app >/dev/null 2>&1; then
    previous_image_id="$(docker container inspect --format '{{.Image}}' rvscope_app)"
    docker image tag "${previous_image_id}" "rvscope-app:rollback-${PREVIOUS_COMMIT}"
    echo "Imagem anterior preservada como rvscope-app:rollback-${PREVIOUS_COMMIT}."
fi

echo "Atualizando ${PROJECT_DIR} a partir de ${REMOTE_NAME}/${BRANCH_NAME}..."
git fetch "${REMOTE_NAME}" "${BRANCH_NAME}"
git pull --ff-only "${REMOTE_NAME}" "${BRANCH_NAME}"

export RVSCOPE_GIT_COMMIT
RVSCOPE_GIT_COMMIT="$(git rev-parse --short=12 HEAD)"

COMPOSE=(
    docker compose
    --file "${BASE_COMPOSE_FILE}"
    --file "${PRODUCTION_COMPOSE_FILE}"
)

echo "Validando a configuração do Compose..."
"${COMPOSE[@]}" config --quiet

echo "Construindo a imagem local do commit ${RVSCOPE_GIT_COMMIT}..."
"${COMPOSE[@]}" build "${SERVICE_NAME}"

echo "Recriando o serviço ${SERVICE_NAME}..."
"${COMPOSE[@]}" up --detach --force-recreate --no-deps "${SERVICE_NAME}"

echo "Aguardando o serviço ficar disponível..."
deadline=$((SECONDS + WAIT_SECONDS))
while (( SECONDS < deadline )); do
    container_id="$("${COMPOSE[@]}" ps --quiet "${SERVICE_NAME}")"

    if [[ -n "${container_id}" ]]; then
        container_status="$(docker inspect --format '{{.State.Status}}' "${container_id}")"
        health_status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}")"

        if [[ "${container_status}" == "running" && "${health_status}" != "starting" ]]; then
            break
        fi
    fi

    sleep 2
done

container_id="$("${COMPOSE[@]}" ps --quiet "${SERVICE_NAME}")"
[[ -n "${container_id}" ]] || fail "O container do serviço ${SERVICE_NAME} não foi criado."

container_status="$(docker inspect --format '{{.State.Status}}' "${container_id}")"
health_status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}")"

if [[ "${container_status}" != "running" || "${health_status}" == "unhealthy" || "${health_status}" == "starting" ]]; then
    "${COMPOSE[@]}" logs --tail=100 "${SERVICE_NAME}" || true
    fail "O serviço não ficou saudável (status=${container_status}, health=${health_status})."
fi

echo "Verificando as migrações registradas..."
"${COMPOSE[@]}" exec -T "${SERVICE_NAME}" php spark migrate:status

echo "Atualização concluída."
"${COMPOSE[@]}" ps
git log -1 --oneline
