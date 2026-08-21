#!/usr/bin/env bash

set -Eeuo pipefail

readonly PROJECT_DIR="${1:-${RVSCOPE_PROJECT_DIR:-/dados/sistemas/rvscope-hom}}"
readonly SERVICE_NAME="${RVSCOPE_SERVICE_NAME:-app}"
readonly HEALTH_URL="${RVSCOPE_HEALTH_URL:-https://127.0.0.1:8443/}"

die() {
    echo "ERRO: $*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Comando obrigatório não encontrado: $1"
}

wait_for_application() {
    local attempt

    for attempt in $(seq 1 30); do
        if curl --fail --insecure --silent --show-error \
            --output /dev/null "$HEALTH_URL"; then
            return 0
        fi
        sleep 2
    done

    return 1
}

require_command curl
require_command docker
require_command git

[[ -d "$PROJECT_DIR/.git" ]] || die "Repositório não encontrado: $PROJECT_DIR"
[[ -f "$PROJECT_DIR/docker-compose.yaml" ]] || die "docker-compose.yaml não encontrado em $PROJECT_DIR"

cd "$PROJECT_DIR"

[[ -z "$(git status --porcelain)" ]] \
    || die "O repositório possui alterações locais. Revise o resultado de: git status"

echo "Baixando atualizações do GitHub..."
git pull --ff-only origin main

echo "Commit selecionado:"
git log -1 --oneline

echo "Validando o Docker Compose..."
docker compose config --quiet

echo "Reconstruindo e recriando o serviço ${SERVICE_NAME}..."
docker compose up --detach --build --force-recreate "$SERVICE_NAME"

echo "Aguardando a aplicação concluir a inicialização e as migrations automáticas..."
if ! wait_for_application; then
    docker compose logs --tail=120 "$SERVICE_NAME" >&2 || true
    die "A aplicação não respondeu em ${HEALTH_URL}."
fi

echo "Conferindo o status das migrations..."
docker compose exec --no-TTY "$SERVICE_NAME" php spark migrate:status

echo "Conferindo o serviço..."
docker compose ps "$SERVICE_NAME"
docker compose logs --tail=100 "$SERVICE_NAME"

echo "Atualização da homologação concluída com sucesso."
