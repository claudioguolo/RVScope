#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_NAME="$(basename "$0")"
readonly PROJECT_DIR="${RVSCOPE_PROJECT_DIR:-/dados/sistemas/rvscope-hom}"
readonly COMPOSE_FILE="${PROJECT_DIR}/docker-compose.yaml"
readonly ENV_FILE="${PROJECT_DIR}/.env"
readonly SERVICE_NAME="app"
readonly CONTAINER_NAME="rvscope_app"
readonly HEALTH_URL="${RVSCOPE_HEALTH_URL:-https://127.0.0.1:8443/}"

usage() {
    cat <<EOF
Uso:
  ${SCRIPT_NAME} <tag-da-imagem>

Exemplo:
  ${SCRIPT_NAME} 8723e04fe759

A tag deve ser a tag imutável de 12 caracteres gerada pelo pipeline do Gitea.
EOF
}

die() {
    echo "ERRO: $*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Comando obrigatório não encontrado: $1"
}

set_image_tag() {
    local tag="$1"
    local temporary_file

    temporary_file="$(mktemp "${PROJECT_DIR}/.env.deploy.XXXXXX")"
    awk -v tag="$tag" '
        BEGIN { updated = 0 }
        /^RVSCOPE_IMAGE_TAG=/ {
            print "RVSCOPE_IMAGE_TAG=" tag
            updated = 1
            next
        }
        { print }
        END {
            if (! updated) {
                print "RVSCOPE_IMAGE_TAG=" tag
            }
        }
    ' "$ENV_FILE" > "$temporary_file"
    chmod --reference="$ENV_FILE" "$temporary_file"
    mv "$temporary_file" "$ENV_FILE"
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

[[ $# -eq 1 ]] || {
    usage
    exit 2
}

readonly NEW_TAG="$1"
[[ "$NEW_TAG" =~ ^[0-9a-f]{12}$ ]] \
    || die "Tag inválida: use o SHA curto de 12 caracteres publicado pelo pipeline."

require_command awk
require_command curl
require_command docker
require_command mktemp

[[ -d "$PROJECT_DIR" ]] || die "Diretório do projeto não encontrado: $PROJECT_DIR"
[[ -f "$COMPOSE_FILE" ]] || die "Compose não encontrado: $COMPOSE_FILE"
[[ -f "$ENV_FILE" ]] || die "Arquivo .env não encontrado: $ENV_FILE"

cd "$PROJECT_DIR"

readonly PREVIOUS_TAG="$(
    awk -F= '/^RVSCOPE_IMAGE_TAG=/{print substr($0, index($0, "=") + 1); exit}' "$ENV_FILE"
)"

echo "Validando a configuração do Docker Compose..."
docker compose --file "$COMPOSE_FILE" config --quiet

echo "Selecionando a imagem RVScope com a tag ${NEW_TAG}..."
set_image_tag "$NEW_TAG"

if ! docker compose --file "$COMPOSE_FILE" pull "$SERVICE_NAME"; then
    set_image_tag "${PREVIOUS_TAG:-homolog}"
    die "Não foi possível baixar a imagem. A tag anterior foi restaurada no .env."
fi

echo "Recriando somente o serviço ${SERVICE_NAME}..."
if ! docker compose --file "$COMPOSE_FILE" up \
    --detach \
    --force-recreate \
    --no-build \
    "$SERVICE_NAME"; then
    set_image_tag "${PREVIOUS_TAG:-homolog}"
    docker compose --file "$COMPOSE_FILE" up \
        --detach \
        --force-recreate \
        --no-build \
        "$SERVICE_NAME" || true
    die "Falha ao recriar a aplicação. Foi tentado o retorno para a tag anterior."
fi

if ! wait_for_application; then
    echo "A aplicação não respondeu em ${HEALTH_URL}." >&2
    docker compose --file "$COMPOSE_FILE" logs --tail=120 "$SERVICE_NAME" >&2 || true
    set_image_tag "${PREVIOUS_TAG:-homolog}"
    docker compose --file "$COMPOSE_FILE" pull "$SERVICE_NAME" || true
    docker compose --file "$COMPOSE_FILE" up \
        --detach \
        --force-recreate \
        --no-build \
        "$SERVICE_NAME" || true
    die "Validação HTTP falhou. Foi tentado o retorno para a tag anterior."
fi

echo "Validando as migrations..."
docker exec "$CONTAINER_NAME" php /var/www/html/spark migrate:status

echo
echo "Deploy concluído com sucesso."
echo "Tag ativa: ${NEW_TAG}"
docker compose --file "$COMPOSE_FILE" ps "$SERVICE_NAME"
