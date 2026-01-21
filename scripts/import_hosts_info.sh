#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: import_hosts_info.sh [--no-truncate] [SQLITE_DB]

Imports hosts_info from a SQLite db into the Postgres hosts_info table.
Default SQLite path: ../hosts_info.db (relative to this script)
EOF
}

truncate=1
sqlite_db=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-truncate)
      truncate=0
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      sqlite_db="$1"
      shift
      ;;
  esac
done

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
root_dir="$(cd -- "${script_dir}/.." && pwd)"
sqlite_db="${sqlite_db:-${root_dir}/hosts_info.db}"

if [[ ! -f "$sqlite_db" ]]; then
  echo "SQLite database not found: $sqlite_db" >&2
  exit 1
fi

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "sqlite3 is required on the host to run this import." >&2
  exit 1
fi

compose=(docker compose -f "${root_dir}/docker-compose.yaml")

if [[ "$truncate" -eq 1 ]]; then
  "${compose[@]}" exec -T db psql -U rvscope -d db_rvscope -c "TRUNCATE TABLE hosts_info;"
fi

sqlite3 -header -csv "$sqlite_db" \
  "select vm, desc, owner, conv, leg, mig, app, creation_date, updated_at from hosts_info;" \
  | "${compose[@]}" exec -T db psql -U rvscope -d db_rvscope \
      -c "\copy hosts_info (vm, desc, owner, conv, leg, mig, app, creation_date, updated_at) FROM STDIN WITH (FORMAT csv, HEADER true)"

"${compose[@]}" exec -T db psql -U rvscope -d db_rvscope -c "select count(*) from hosts_info;"
