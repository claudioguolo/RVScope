#!/usr/bin/env bash
set -euo pipefail

OLD_NAME="${1:-Red Hat Enterprise Linux 1}"
NEW_NAME="${2:-Red Hat Enterprise Linux 10}"

PG_DB="${PG_DB:-rvscope}"
PG_USER="${PG_USER:-rvscope}"

echo "Corrigindo nomes de SO no banco..."
echo "De: ${OLD_NAME}"
echo "Para: ${NEW_NAME}"

docker compose exec -T db psql -v ON_ERROR_STOP=1 -U "$PG_USER" -d "$PG_DB" <<SQL
BEGIN;

UPDATE rvtools_os_summary
SET os_name = '${NEW_NAME}'
WHERE os_name = '${OLD_NAME}';

UPDATE rvtools_vm_inventory
SET os_name = '${NEW_NAME}'
WHERE os_name = '${OLD_NAME}';

COMMIT;
SQL

echo "Correcao concluida."
