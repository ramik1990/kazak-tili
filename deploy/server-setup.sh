#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/uchebnik}"
REPO="${REPO:-git@github-uchebnik:ramik1990/kazak-tili.git}"
APP_PORT="${APP_PORT:-8082}"
DB_DATABASE="${DB_DATABASE:-uchebnik}"
DB_USERNAME="${DB_USERNAME:-uchebnik}"
DB_PASSWORD="${DB_PASSWORD:-Core2quad!}"

if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi

if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" pull --ff-only
else
    git clone "$REPO" "$APP_DIR"
fi

cd "$APP_DIR"

IP="$(curl -4 -s --max-time 5 ifconfig.me || hostname -I | awk '{print $1}')"

if [ ! -f .env ]; then
    cp .env.example .env
    printf 'APP_PORT=%s\nDB_ROOT_PASSWORD="%s"\n' "$APP_PORT" "$DB_PASSWORD" >> .env
    sed -i "s#^APP_NAME=.*#APP_NAME=\"Qazaq Tili\"#; s#^APP_ENV=.*#APP_ENV=production#; s#^APP_DEBUG=.*#APP_DEBUG=false#; s#^APP_URL=.*#APP_URL=http://$IP:$APP_PORT#" .env
    sed -i "s#^DB_HOST=.*#DB_HOST=db#; s#^DB_DATABASE=.*#DB_DATABASE=$DB_DATABASE#; s#^DB_USERNAME=.*#DB_USERNAME=$DB_USERNAME#; s#^DB_PASSWORD=.*#DB_PASSWORD=\"$DB_PASSWORD\"#" .env
fi

mkdir -p public/build public/illustrations storage/app/public/audio

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q '^Status: active'; then
    ufw allow "$APP_PORT"/tcp >/dev/null
fi

docker compose up -d --build
docker compose ps

echo
echo "Готово: http://$IP:$APP_PORT"
