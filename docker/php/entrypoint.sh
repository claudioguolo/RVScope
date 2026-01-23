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

DB_NAME="${DB_NAME:-rvscope}"
DB_USER="${DB_USER:-rvscope}"
DB_PASS="${DB_PASS:-rvscope_password}"
DB_HOST="${DB_HOST:-db}"

# 1. Aguardar o banco de dados estar pronto
echo "Aguardando o serviço de banco de dados ($DB_HOST:5432)..."
export PGPASSWORD="$DB_PASS"
until pg_isready -h "$DB_HOST" -U "$DB_USER"; do
  echo "Postgres ainda está iniciando ou inacessível - dormindo 2s..."
  sleep 2
done

# 2. Instalação inicial do CodeIgniter (caso a pasta esteja vazia)
if [ ! -f "$APP_ROOT/public/index.php" ]; then
    echo "Pasta vazia detectada. Instalando CodeIgniter 4..."
    TEMP_DIR="/tmp/ci4_app"
    rm -rf "$TEMP_DIR"
    composer create-project codeigniter4/appstarter "$TEMP_DIR" --no-dev --no-interaction

    # Backup do .env se existir no host mas não no container
    if [ -f "$APP_ROOT/.env" ]; then
        cp "$APP_ROOT/.env" /tmp/app.env
    fi

    cp -a "$TEMP_DIR/." "$APP_ROOT/"
    
    if [ -f "/tmp/app.env" ]; then
        cp /tmp/app.env "$APP_ROOT/.env"
    fi
    rm -rf "$TEMP_DIR"
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
             "$APP_ROOT/app/Database/Migrations" "$APP_ROOT/app/Views"

    # Sincroniza subpastas se existirem
    for dir in Controllers Models Libraries Database/Migrations Views public; do
        if [ -d "$CUSTOM_APP/$dir" ]; then
            cp -a "$CUSTOM_APP/$dir/." "$APP_ROOT/app/${dir#app/}"
        fi
    done

    # Sincroniza arquivos de Configuração específicos
    [ -f "$CUSTOM_APP/Config/Routes.php" ] && cp -a "$CUSTOM_APP/Config/Routes.php" "$APP_ROOT/app/Config/Routes.php"
    [ -f "$CUSTOM_APP/Config/Rvtools.php" ] && cp -a "$CUSTOM_APP/Config/Rvtools.php" "$APP_ROOT/app/Config/Rvtools.php"
fi

# 5. Executar Migrations (Sincroniza tabelas do banco)
echo "Executando migrações do banco de dados..."
cd "$APP_ROOT"
# Tenta rodar as migrations. Se falhar por já estarem migradas, não trava o script.
php spark migrate --all || echo "Aviso: Falha ao rodar migrations ou tabelas já atualizadas."

# 6. AJUSTE FINAL DE PERMISSÕES (Crucial para evitar erro 403 e 500)
echo "Ajustando permissões para o Apache (www-data)..."
chown -R www-data:www-data "$APP_ROOT"
find "$APP_ROOT" -type d -exec chmod 755 {} +
find "$APP_ROOT" -type f -exec chmod 644 {} +
chmod -R 775 "$APP_ROOT/writable"

echo "Setup concluído com sucesso. Iniciando Apache..."
exec apache2-foreground
