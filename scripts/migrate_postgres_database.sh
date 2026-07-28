#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_NAME="$(basename "$0")"

SOURCE_CONTAINER="${SOURCE_CONTAINER:-rvscope_db}"
SOURCE_DATABASE="${SOURCE_DATABASE:-rvscope_db}"
TARGET_HOST="${TARGET_HOST:-10.0.118.17}"
TARGET_PORT="${TARGET_PORT:-5432}"
TARGET_DATABASE="${TARGET_DATABASE:-RVScopeHom}"
TARGET_USER="${TARGET_USER:-UserRVScopeHom}"
BACKUP_DIR="${BACKUP_DIR:-/dados/backups/rvscope/migrations}"
CONFIRMATION=""
EXECUTE=false
TARGET_DB_PASSWORD="${TARGET_DB_PASSWORD:-}"
TARGET_PGPASS_FILE=""

usage() {
    cat <<EOF
Uso:
  ${SCRIPT_NAME} [opções]

Opções:
  --source-container NOME   Container PostgreSQL de origem
                            (padrão: ${SOURCE_CONTAINER})
  --source-database NOME    Banco de origem (padrão: ${SOURCE_DATABASE})
  --target-host HOST        Servidor de destino (padrão: ${TARGET_HOST})
  --target-port PORTA       Porta de destino (padrão: ${TARGET_PORT})
  --target-database NOME    Banco de destino (padrão: ${TARGET_DATABASE})
  --target-user USUARIO     Usuário de destino (padrão: ${TARGET_USER})
  --backup-dir DIRETORIO    Diretório protegido para o dump
                            (padrão: ${BACKUP_DIR})
  --execute                 Autoriza a gravação no banco de destino
  --confirm NOME            Deve ser exatamente o nome do banco de destino
  --help                    Exibe esta ajuda

A senha do destino é solicitada sem eco. Como alternativa, disponibilize-a
temporariamente em TARGET_DB_PASSWORD sem gravá-la em arquivos ou no Git.
EOF
}

die() {
    echo "ERRO: $*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 \
        || die "Comando obrigatório não encontrado: $1"
}

cleanup() {
    if [[ -n "$TARGET_PGPASS_FILE" ]]; then
        docker exec "$SOURCE_CONTAINER" \
            rm -f "$TARGET_PGPASS_FILE" >/dev/null 2>&1 || true
    fi
    unset TARGET_DB_PASSWORD
}

pgpass_escape() {
    local value="$1"

    value="${value//\\/\\\\}"
    value="${value//:/\\:}"
    printf '%s' "$value"
}

target_psql() {
    docker exec \
        -e "PGPASSFILE=${TARGET_PGPASS_FILE}" \
        "$SOURCE_CONTAINER" \
        psql \
        --no-psqlrc \
        --set ON_ERROR_STOP=1 \
        --host "$TARGET_HOST" \
        --port "$TARGET_PORT" \
        --username "$TARGET_USER" \
        --dbname "$TARGET_DATABASE" \
        "$@"
}

source_psql() {
    docker exec "$SOURCE_CONTAINER" \
        sh -c '
            exec psql \
                --no-psqlrc \
                --set ON_ERROR_STOP=1 \
                --username "$POSTGRES_USER" \
                --dbname "$1" \
                "$@"
        ' sh "$SOURCE_DATABASE" "$@"
}

table_count() {
    local side="$1"
    local table="$2"
    local sql

    sql="SELECT CASE
        WHEN to_regclass('public.${table}') IS NULL THEN '-'
        ELSE (SELECT COUNT(*)::text FROM public.${table})
    END;"

    if [[ "$side" == "source" ]]; then
        source_psql --tuples-only --no-align --command "$sql"
    else
        target_psql --tuples-only --no-align --command "$sql"
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --source-container)
            [[ $# -ge 2 ]] || die "Valor ausente para --source-container"
            SOURCE_CONTAINER="$2"
            shift 2
            ;;
        --source-database)
            [[ $# -ge 2 ]] || die "Valor ausente para --source-database"
            SOURCE_DATABASE="$2"
            shift 2
            ;;
        --target-host)
            [[ $# -ge 2 ]] || die "Valor ausente para --target-host"
            TARGET_HOST="$2"
            shift 2
            ;;
        --target-port)
            [[ $# -ge 2 ]] || die "Valor ausente para --target-port"
            TARGET_PORT="$2"
            shift 2
            ;;
        --target-database)
            [[ $# -ge 2 ]] || die "Valor ausente para --target-database"
            TARGET_DATABASE="$2"
            shift 2
            ;;
        --target-user)
            [[ $# -ge 2 ]] || die "Valor ausente para --target-user"
            TARGET_USER="$2"
            shift 2
            ;;
        --backup-dir)
            [[ $# -ge 2 ]] || die "Valor ausente para --backup-dir"
            BACKUP_DIR="$2"
            shift 2
            ;;
        --execute)
            EXECUTE=true
            shift
            ;;
        --confirm)
            [[ $# -ge 2 ]] || die "Valor ausente para --confirm"
            CONFIRMATION="$2"
            shift 2
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            die "Opção desconhecida: $1"
            ;;
    esac
done

require_command docker
require_command sha256sum

[[ "$TARGET_PORT" =~ ^[0-9]+$ ]] || die "Porta de destino inválida."
[[ "$SOURCE_DATABASE" != "$TARGET_DATABASE" || "$TARGET_HOST" != "127.0.0.1" ]] \
    || die "Origem e destino não podem ser o mesmo banco."

docker inspect "$SOURCE_CONTAINER" >/dev/null 2>&1 \
    || die "Container de origem não encontrado: ${SOURCE_CONTAINER}"

[[ "$(docker inspect "$SOURCE_CONTAINER" --format '{{.State.Running}}')" == "true" ]] \
    || die "Container de origem não está em execução: ${SOURCE_CONTAINER}"

if [[ -z "$TARGET_DB_PASSWORD" ]]; then
    read -r -s -p "Senha de ${TARGET_USER} em ${TARGET_HOST}: " TARGET_DB_PASSWORD
    echo
fi
[[ -n "$TARGET_DB_PASSWORD" ]] || die "Senha de destino não informada."

TARGET_PGPASS_FILE="/tmp/rvscope-pgpass-$$"
trap cleanup EXIT

printf '%s:%s:%s:%s:%s\n' \
    "$(pgpass_escape "$TARGET_HOST")" \
    "$(pgpass_escape "$TARGET_PORT")" \
    "$(pgpass_escape "$TARGET_DATABASE")" \
    "$(pgpass_escape "$TARGET_USER")" \
    "$(pgpass_escape "$TARGET_DB_PASSWORD")" |
    docker exec -i "$SOURCE_CONTAINER" \
        sh -c 'umask 077; cat > "$1"' sh "$TARGET_PGPASS_FILE"

echo "Validando o banco de origem..."
SOURCE_VERSION="$(source_psql --tuples-only --no-align --command 'SHOW server_version;')"
SOURCE_TABLES="$(source_psql --tuples-only --no-align --command \
    "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public';")"

echo "Validando conexão e banco de destino..."
TARGET_VERSION="$(target_psql --tuples-only --no-align --command 'SHOW server_version;')"
TARGET_TABLES="$(target_psql --tuples-only --no-align --command \
    "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public';")"

echo "Origem: ${SOURCE_CONTAINER}/${SOURCE_DATABASE}, PostgreSQL ${SOURCE_VERSION}, tabelas públicas: ${SOURCE_TABLES}"
echo "Destino: ${TARGET_HOST}:${TARGET_PORT}/${TARGET_DATABASE}, PostgreSQL ${TARGET_VERSION}, tabelas públicas: ${TARGET_TABLES}"

[[ "$TARGET_TABLES" == "0" ]] \
    || die "O banco de destino não está vazio. Nenhum dado foi alterado."

if [[ "$EXECUTE" != "true" ]]; then
    echo "Pré-validação concluída. Nenhum dado foi alterado."
    echo "Para executar, adicione: --execute --confirm '${TARGET_DATABASE}'"
    exit 0
fi

[[ "$CONFIRMATION" == "$TARGET_DATABASE" ]] \
    || die "Confirmação inválida. Informe --confirm '${TARGET_DATABASE}'."

umask 077
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

TIMESTAMP="$(date +%Y-%m-%d_%H%M%S)"
DUMP_FILE="${BACKUP_DIR}/${SOURCE_DATABASE}_para_${TARGET_DATABASE}_${TIMESTAMP}.dump"
CHECKSUM_FILE="${DUMP_FILE}.sha256"

echo "Gerando snapshot consistente da origem em ${DUMP_FILE}..."
if ! docker exec "$SOURCE_CONTAINER" \
    sh -c '
        exec pg_dump \
            --format=custom \
            --no-owner \
            --no-acl \
            --username "$POSTGRES_USER" \
            --dbname "$1"
    ' sh "$SOURCE_DATABASE" > "$DUMP_FILE"; then
    die "Falha ao gerar o dump da origem."
fi

[[ -s "$DUMP_FILE" ]] || die "O dump gerado está vazio."

docker exec -i "$SOURCE_CONTAINER" pg_restore --list \
    < "$DUMP_FILE" >/dev/null \
    || die "O catálogo do dump não pôde ser validado."

sha256sum "$DUMP_FILE" > "$CHECKSUM_FILE"
chmod 600 "$DUMP_FILE" "$CHECKSUM_FILE"

echo "Restaurando no destino em uma única transação..."
if ! docker exec \
    -i \
    -e "PGPASSFILE=${TARGET_PGPASS_FILE}" \
    "$SOURCE_CONTAINER" \
    pg_restore \
    --host "$TARGET_HOST" \
    --port "$TARGET_PORT" \
    --username "$TARGET_USER" \
    --dbname "$TARGET_DATABASE" \
    --no-owner \
    --no-acl \
    --exit-on-error \
    --single-transaction \
    < "$DUMP_FILE"; then
    die "Restauração recusada ou revertida. O dump foi preservado em ${DUMP_FILE}."
fi

echo "Comparando tabelas principais..."
printf '%-32s %12s %12s\n' "Tabela" "Origem" "Destino"

MISMATCHES=0
for table in \
    migrations \
    admin_users \
    app_settings \
    hosts_info \
    rvtools_import_log \
    rvtools_os_summary \
    rvtools_vm_inventory; do
    SOURCE_COUNT="$(table_count source "$table")"
    TARGET_COUNT="$(table_count target "$table")"
    printf '%-32s %12s %12s\n' "$table" "$SOURCE_COUNT" "$TARGET_COUNT"

    if [[ "$SOURCE_COUNT" != "$TARGET_COUNT" ]]; then
        MISMATCHES=$((MISMATCHES + 1))
    fi
done

TARGET_TABLES_AFTER="$(target_psql --tuples-only --no-align --command \
    "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public';")"

echo "Tabelas públicas no destino após restauração: ${TARGET_TABLES_AFTER}"
echo "Dump preservado: ${DUMP_FILE}"
echo "Checksum: ${CHECKSUM_FILE}"

[[ "$MISMATCHES" -eq 0 ]] \
    || die "Restauração concluída, mas há divergências nas contagens."

echo "Migração concluída e contagens principais validadas."
