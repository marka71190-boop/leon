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
if ! ${DEPLOY_CMD:-docker compose up -d --build}; then
  # Сборка не удалась (например, нет связи) — сайт работает на прежней версии, повторим через 2 минуты
  git reset -q --hard "$LOCAL"
  echo "$(date '+%F %T') ошибка сборки, сайт остался на прежней версии — повтор при следующем запуске"
  exit 1
fi
docker image prune -f >/dev/null 2>&1
echo "$(date '+%F %T') готово"
