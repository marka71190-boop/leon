#!/bin/sh
# Автообновление сайта: если на GitHub появились изменения — скачать и пересобрать.
# Запускается по расписанию (cron) раз в 2 минуты. База и фото хранятся в томах Docker и не трогаются.
APP_DIR="${APP_DIR:-/root/leonpro}"
cd "$APP_DIR" || exit 1

exec 9>/tmp/leonpro-deploy.lock
flock -n 9 || exit 0          # предыдущее обновление ещё идёт

git fetch -q origin || { echo "$(date '+%F %T') ошибка: нет доступа к GitHub"; exit 1; }
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse '@{u}')
[ "$LOCAL" = "$REMOTE" ] && exit 0

echo "$(date '+%F %T') обновление ${LOCAL%"${LOCAL#???????}"} -> ${REMOTE%"${REMOTE#???????}"}"
git reset -q --hard "$REMOTE"
${DEPLOY_CMD:-docker compose up -d --build} && docker image prune -f >/dev/null 2>&1
echo "$(date '+%F %T') готово"
