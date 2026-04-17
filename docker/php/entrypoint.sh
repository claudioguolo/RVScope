#!/bin/bash
set -e

APP_ROOT="/var/www/html"
CUSTOM_APP="/srv/app"

get_env_value() {
  local key="$1"
  if [ -f "$APP_ROOT/.env" ]; then
    grep -E "^[[:space:]]*${key}[[:space:]]*=" "$APP_ROOT/.env" \
      | tail -n 1 \
      | sed -E "s/^[[:space:]]*${key}[[:space:]]*=[[:space:]]*//" \
      | sed -E 's/[[:space:]]+#.*$//' \
      | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//' \
      | sed -E 's/^"//; s/"$//'
  fi
}

DB_NAME="$(get_env_value 'database.default.database')"
DB_USER="$(get_env_value 'database.default.username')"
DB_PASS="$(get_env_value 'database.default.password')"
DB_HOST="$(get_env_value 'database.default.hostname')"

DB_NAME="${DB_NAME:-${POSTGRES_DB:-rvscope}}"
DB_USER="${DB_USER:-${POSTGRES_USER:-rvscope}}"
DB_PASS="${DB_PASS:-${POSTGRES_PASSWORD:-}}"
DB_HOST="${DB_HOST:-${POSTGRES_HOST:-db}}"

if [ -z "$DB_PASS" ]; then
  echo "Erro: senha do banco nao definida. Configure database.default.password no .env (ou POSTGRES_PASSWORD)." >&2
  exit 1
fi

# 1. Aguardar o banco de dados estar pronto
echo "Aguardando o serviço de banco de dados ($DB_HOST:5432)..."
export PGPASSWORD="$DB_PASS"
until pg_isready -h "$DB_HOST" -U "$DB_USER"; do
  echo "Postgres ainda está iniciando ou inacessível - dormindo 2s..."
  sleep 2
done

# 2. Restauracao inicial do CodeIgniter (caso a pasta esteja vazia)
if [ ! -f "$APP_ROOT/public/index.php" ]; then
    if [ ! -f "/opt/ci4-app/public/index.php" ]; then
        echo "Erro: base CodeIgniter nao encontrada em /opt/ci4-app dentro da imagem." >&2
        exit 1
    fi

    echo "Pasta vazia detectada. Restaurando base CodeIgniter 4 da imagem..."

    # Backup do .env se existir no host mas não no container
    if [ -f "$APP_ROOT/.env" ]; then
        cp "$APP_ROOT/.env" /tmp/app.env
    fi

    shopt -s dotglob
    for item in /opt/ci4-app/*; do
        [ "$(basename "$item")" = "writable" ] && continue
        cp -R "$item" "$APP_ROOT/"
    done
    shopt -u dotglob
    mkdir -p "$APP_ROOT/writable"/{cache,debugbar,logs,session,uploads}
    touch "$APP_ROOT/writable/index.html"
    
    if [ -f "/tmp/app.env" ]; then
        cp /tmp/app.env "$APP_ROOT/.env"
    fi
fi

# 3. Criação automática do Banco de Dados caso não exista
echo "Verificando se o banco $DB_NAME existe..."
DB_EXISTS=$(psql -h "$DB_HOST" -U "$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'")

if [ "$DB_EXISTS" != "1" ]; then
    echo "Criando banco de dados $DB_NAME..."
    psql -h "$DB_HOST" -U "$DB_USER" -d postgres -c "CREATE DATABASE $DB_NAME;"
else
    echo "Banco de dados $DB_NAME já existe."
fi

# 4. Sincronização de arquivos customizados do Volume /srv/app
if [ -d "$CUSTOM_APP" ]; then
    echo "Sincronizando arquivos de $CUSTOM_APP para $APP_ROOT..."
    mkdir -p "$APP_ROOT/app/Controllers" "$APP_ROOT/app/Models" "$APP_ROOT/app/Libraries" \
             "$APP_ROOT/app/Database/Migrations" "$APP_ROOT/app/Views" "$APP_ROOT/app/Config" \
             "$APP_ROOT/public"

    # Sincroniza subpastas se existirem
    for dir in Controllers Models Libraries Database/Migrations Views; do
        if [ -d "$CUSTOM_APP/$dir" ]; then
            cp -R "$CUSTOM_APP/$dir/." "$APP_ROOT/app/$dir"
        fi
    done

    if [ -d "$CUSTOM_APP/public" ]; then
        cp -R "$CUSTOM_APP/public/." "$APP_ROOT/public/"
    fi

    if [ -d "$CUSTOM_APP/Config" ]; then
        for cfg in "$CUSTOM_APP"/Config/*.php; do
            if [ -f "$cfg" ]; then
                cp -R "$cfg" "$APP_ROOT/app/Config/"
            fi
        done
    fi
fi

# 5. Executar Migrations (Sincroniza tabelas do banco)
echo "Executando migrações do banco de dados..."
cd "$APP_ROOT"
# Tenta rodar as migrations. Se falhar por já estarem migradas, não trava o script.
php spark migrate --all || echo "Aviso: Falha ao rodar migrations ou tabelas já atualizadas."

# 6. AJUSTE FINAL DE PERMISSÕES (Crucial para evitar erro 403 e 500)
echo "Ajustando permissões para o Apache (www-data)..."
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "$APP_ROOT" || true
else
    echo "Aviso: container sem privilegio de root; pulando chown."
fi
find "$APP_ROOT" -type d -exec chmod 755 {} + || true
find "$APP_ROOT" -type f -exec chmod 644 {} + || true
if [ -d "$APP_ROOT/writable" ]; then
    chmod -R 775 "$APP_ROOT/writable" || true
fi

echo "Setup concluído com sucesso. Iniciando Apache..."
exec apache2-foreground
