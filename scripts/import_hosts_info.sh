#!/usr/bin/env bash
set -euo pipefail

SQLITE_PATH="${1:-}"
if [ -z "$SQLITE_PATH" ]; then
  echo "Uso: $0 /caminho/hosts_info.db" >&2
  exit 1
fi

if [ ! -f "$SQLITE_PATH" ]; then
  echo "Arquivo nao encontrado: $SQLITE_PATH" >&2
  exit 1
fi

PG_DB="${PG_DB:-rvscope}"
PG_USER="${PG_USER:-rvscope}"

TMP_DB="$(mktemp)"
trap 'rm -f "$TMP_DB"' EXIT
cp "$SQLITE_PATH" "$TMP_DB"

python - <<'PY' "$TMP_DB" \
| docker compose exec -T db psql -U "$PG_USER" -d "$PG_DB"
import sqlite3
import sys

path = sys.argv[1]

conn = sqlite3.connect(path)
conn.row_factory = sqlite3.Row
conn.text_factory = lambda b: b.decode('utf-8', 'replace')

rows = conn.execute(
    "SELECT vm, \"desc\", owner, conv, leg, mig, app, creation_date, updated_at FROM hosts_info"
).fetchall()

def esc(value):
    if value is None:
        return 'NULL'
    if isinstance(value, (int, float)):
        return str(int(value))
    s = str(value)
    s = s.replace("'", "''")
    return "'" + s + "'"

print('BEGIN;')
print('-- Migracao hosts_info (SQLite -> Postgres)')

for row in rows:
    vm = esc(row['vm'])
    desc = esc(row['desc'])
    owner = esc(row['owner'])
    conv = esc(row['conv'])
    leg = esc(row['leg'])
    mig = esc(row['mig'])
    app = esc(row['app'])
    creation_date = esc(row['creation_date'])
    updated_at = esc(row['updated_at'])

    print(
        'INSERT INTO hosts_info (vm, "desc", owner, conv, leg, mig, app, creation_date, updated_at) VALUES ('
        f"{vm}, {desc}, {owner}, {conv}, {leg}, {mig}, {app}, {creation_date}, {updated_at}"
        ')\n'
        'ON CONFLICT (vm) DO UPDATE SET '
        '"desc" = EXCLUDED."desc", '
        'owner = EXCLUDED.owner, '
        'conv = EXCLUDED.conv, '
        'leg = EXCLUDED.leg, '
        'mig = EXCLUDED.mig, '
        'app = EXCLUDED.app, '
        'creation_date = EXCLUDED.creation_date, '
        'updated_at = EXCLUDED.updated_at;'
    )

print('COMMIT;')
PY

echo "Importacao concluida."
