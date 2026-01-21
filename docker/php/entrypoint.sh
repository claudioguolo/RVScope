#!/bin/sh
set -e

APP_ROOT="/var/www/html"
CUSTOM_APP="/srv/app"

if [ ! -f "$APP_ROOT/public/index.php" ]; then
    TEMP_DIR="/tmp/ci4_app"
    rm -rf "$TEMP_DIR"
    composer create-project codeigniter4/appstarter "$TEMP_DIR" --no-dev

    ENV_BACKUP=""
    if [ -f "$APP_ROOT/.env" ]; then
        cp "$APP_ROOT/.env" /tmp/app.env
        ENV_BACKUP="1"
    fi

    cp -a "$TEMP_DIR/." "$APP_ROOT/"

    if [ "$ENV_BACKUP" = "1" ]; then
        cp /tmp/app.env "$APP_ROOT/.env"
    fi

    rm -rf "$TEMP_DIR"
fi

if [ -d "$CUSTOM_APP" ]; then
    mkdir -p "$APP_ROOT/app/Controllers" \
        "$APP_ROOT/app/Models" \
        "$APP_ROOT/app/Libraries" \
        "$APP_ROOT/app/Database/Migrations" \
        "$APP_ROOT/app/Views"

    if [ -d "$CUSTOM_APP/Controllers" ]; then
        cp -a "$CUSTOM_APP/Controllers/." "$APP_ROOT/app/Controllers/"
    fi
    if [ -d "$CUSTOM_APP/Models" ]; then
        cp -a "$CUSTOM_APP/Models/." "$APP_ROOT/app/Models/"
    fi
    if [ -d "$CUSTOM_APP/Libraries" ]; then
        cp -a "$CUSTOM_APP/Libraries/." "$APP_ROOT/app/Libraries/"
    fi
    if [ -d "$CUSTOM_APP/Database/Migrations" ]; then
        cp -a "$CUSTOM_APP/Database/Migrations/." "$APP_ROOT/app/Database/Migrations/"
    fi
    if [ -d "$CUSTOM_APP/Views" ]; then
        cp -a "$CUSTOM_APP/Views/." "$APP_ROOT/app/Views/"
    fi
    if [ -d "$CUSTOM_APP/public" ]; then
        cp -a "$CUSTOM_APP/public/." "$APP_ROOT/public/"
    fi

    if [ -f "$CUSTOM_APP/Config/Routes.php" ]; then
        cp -a "$CUSTOM_APP/Config/Routes.php" "$APP_ROOT/app/Config/Routes.php"
    fi
    if [ -f "$CUSTOM_APP/Config/Rvtools.php" ]; then
        cp -a "$CUSTOM_APP/Config/Rvtools.php" "$APP_ROOT/app/Config/Rvtools.php"
    fi
fi

chown -R www-data:www-data "$APP_ROOT/writable" || true

exec apache2-foreground
