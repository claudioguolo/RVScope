#!/usr/bin/env bash
set -euo pipefail
umask 077

: "${RVSCOPE_ERRATA_URL:?Defina a URL HTTPS completa de /api/erratas/por-dia}"
: "${RVSCOPE_ERRATA_IMPORT_TOKEN:?Defina o token exclusivo da coleta de erratas}"

if [[ "$RVSCOPE_ERRATA_URL" != https://* || ${#RVSCOPE_ERRATA_IMPORT_TOKEN} -lt 32 || "$RVSCOPE_ERRATA_IMPORT_TOKEN" == *$'\n'* || "$RVSCOPE_ERRATA_IMPORT_TOKEN" == *$'\r'* ]]; then
    echo 'Configure uma URL HTTPS e um token válido de pelo menos 32 caracteres.' >&2
    exit 1
fi

satellite_organization="${SATELLITE_ORGANIZATION:-TJMT}"
# full-result evita contar somente a primeira página. Confirme suporte no Hammer instalado.
satellite_output=$(LC_ALL=C hammer erratum list \
    --organization "$satellite_organization" \
    --errata-restrict-installable true \
    --full-result true)

# Mantém a coluna TYPE usada pelo script anterior, mas valida o cabeçalho e emite zeros explícitos.
errata_data=$(printf '%s\n' "$satellite_output" | awk -F '|' '
    {
        type=$3
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", type)
        type=tolower(type)
    }
    type == "type" { found_header=1; next }
    type == "bugfix" || type == "enhancement" || type == "security" || type == "unspecified" || type == "other" { counts[type]++ }
    END {
        if (!found_header) { print "Cabeçalho TYPE não encontrado na saída do Hammer." > "/dev/stderr"; exit 1 }
        printf "%d bugfix ; %d enhancement ; %d security ; %d unspecified ;", counts["bugfix"], counts["enhancement"], counts["security"], counts["unspecified"]+counts["other"]
    }')

collection_date=$(date -u +'%d-%m-%Y%H:%M')
header_file=$(mktemp)
trap 'rm -f "$header_file"' EXIT
printf 'Authorization: Bearer %s\n' "$RVSCOPE_ERRATA_IMPORT_TOKEN" > "$header_file"

curl_options=(--fail --silent --show-error --proto '=https' --connect-timeout 10 --max-time 60
    --retry 2 --retry-delay 2 --header "@$header_file")
if [[ -n "${RVSCOPE_HTTPS_CA_FILE:-}" ]]; then
    curl_options+=(--cacert "$RVSCOPE_HTTPS_CA_FILE")
fi

curl "${curl_options[@]}" \
    --data-urlencode "data=$collection_date" \
    --data-urlencode "dados=$errata_data" \
    "$RVSCOPE_ERRATA_URL"
printf '\n'
