#!/bin/sh
# Сброс пароля администратора сайта (если забыли пароль от админки).
# Запуск на сервере: cd /root/leonpro && sh deploy/admin-password.sh
cd "$(dirname "$0")/.." || exit 1

echo "Администраторы сайта:"
docker compose exec -T web php -r '
$f = glob("/var/www/html/data/db-*.sqlite")[0] ?? null;
if (!$f) { fwrite(STDERR, "База сайта не найдена\n"); exit(1); }
$p = new PDO("sqlite:" . $f);
foreach ($p->query("SELECT login, is_owner FROM admins ORDER BY id") as $r) {
    echo "  ", $r["login"], !empty($r["is_owner"]) ? "  (главный)" : "", "\n";
}
' || exit 1

printf "Логин, которому сменить пароль: "
read LOGIN
stty -echo 2>/dev/null
printf "Новый пароль (не короче 8 символов, при вводе не виден): "
read PASS
stty echo 2>/dev/null
echo
if [ ${#PASS} -lt 8 ]; then
    echo "Пароль короче 8 символов — ничего не изменено."
    exit 1
fi

docker compose exec -T -e LOGIN="$LOGIN" -e PASS="$PASS" web php -r '
$f = glob("/var/www/html/data/db-*.sqlite")[0];
$p = new PDO("sqlite:" . $f);
$st = $p->prepare("UPDATE admins SET password_hash = ? WHERE login = ?");
$st->execute([password_hash(getenv("PASS"), PASSWORD_DEFAULT), getenv("LOGIN")]);
echo $st->rowCount() ? "Готово: пароль изменён. Входите на /admin с новым паролем.\n" : "Логин не найден — ничего не изменено.\n";
'
