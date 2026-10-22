#!/usr/bin/env bash
# Upgrade rehearsal: install an OLD release with representative data on a
# legacy-mode MariaDB (MyISAM default, explicit_defaults_for_timestamp=0),
# back it up, upgrade it with THIS checkout exactly as hosting support would,
# verify, then restore the backup and check the old release runs again.
#
# usage: tools/upgrade-rehearsal/run.sh <old-git-ref> <label>
# needs: mariadb client + server on 127.0.0.1:3307 (root/root), php8.4, git
set -euo pipefail
OLD_REF="$1"; LABEL="$2"
NEW_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${REHEARSAL_WORK:-/tmp/iet-rehearsal}/$LABEL"
OLD_DIR="$WORK/old"
DB="iet_up_${LABEL//[^a-zA-Z0-9]/_}"
PHP="${PHP:-php8.4} -d xdebug.mode=off -d memory_limit=1G"
KEY="base64:$(printf 'rehearsal-key-0123456789abcdef!!' | base64)"
mysql_() { mariadb -h127.0.0.1 -P3307 -uroot -proot "$@"; }

rm -rf "$WORK"; mkdir -p "$WORK"
git -C "$NEW_DIR" worktree remove --force "$OLD_DIR" 2>/dev/null || true
git -C "$NEW_DIR" worktree add --detach -q "$OLD_DIR" "$OLD_REF"
# A real copy: a symlinked vendor would autoload the NEW app classes.
cp -a "$NEW_DIR/vendor" "$OLD_DIR/vendor"
( cd "$OLD_DIR" && composer dump-autoload -q --no-scripts )
mysql_ -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

export APP_KEY="$KEY" DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE="$DB" DB_USERNAME=root DB_PASSWORD=root DB_ENGINE=InnoDB \
       SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=sync BROADCAST_CONNECTION=log MAIL_MAILER=array \
       IET_RELEASE_PROFILE=full MEDIA_SCANNER_ENABLED=false MEDIA_SCANNER_REQUIRED=false
cp "$OLD_DIR/.env.example" "$OLD_DIR/.env"

echo "== 1. Install $OLD_REF with representative data"
( cd "$OLD_DIR" && APP_ENV=local IET_SEED_DEMO_DATA=true CACHE_STORE=array $PHP artisan migrate --force -q \
  && APP_ENV=local IET_SEED_DEMO_DATA=true $PHP artisan db:seed --force -q \
  && APP_ENV=local $PHP artisan db:seed --class=ProfessionSeeder --force -q \
  && { APP_ENV=local $PHP artisan db:seed --class=RealEstateBusinessDemoSeeder --force -q || echo "(no real-estate demo in this version)"; } )
( cd "$OLD_DIR" && APP_ENV=local $PHP "$NEW_DIR/tools/upgrade-rehearsal/seed-old.php" "$OLD_DIR" )
( cd "$OLD_DIR" && APP_ENV=production $PHP "$NEW_DIR/tools/upgrade-rehearsal/snapshot.php" "$OLD_DIR" > "$WORK/before.json" )

echo "== 2. Backup (mysqldump, as in the backup procedure)"
mariadb-dump -h127.0.0.1 -P3307 -uroot -proot --single-transaction --routines --triggers --default-character-set=utf8mb4 "$DB" > "$WORK/backup.sql"
ls -l "$WORK/backup.sql" | awk '{print "backup bytes: "$5}'

echo "== 3. Upgrade with this checkout (exact hosting-support commands)"
cd "$NEW_DIR"
export APP_ENV=production APP_DEBUG=false
$PHP artisan optimize:clear -q
$PHP artisan iet:inventory --json > "$WORK/inventory-before.json"
$PHP artisan migrate --force
$PHP artisan iet:taxonomy:sync
$PHP artisan iet:inventory --json > "$WORK/inventory-after.json"
$PHP artisan migrate:status | grep -ci pending | xargs -I{} echo "pending after upgrade: {}" || true
$PHP tools/upgrade-rehearsal/snapshot.php "$NEW_DIR" > "$WORK/after.json"
$PHP tools/upgrade-rehearsal/verify.php "$NEW_DIR" "$WORK/before.json" "$WORK/after.json" | tee "$WORK/verify.txt"
echo "== 3b. Second sync is a no-op"
$PHP artisan iet:taxonomy:sync | tail -2
$PHP artisan optimize:clear -q

echo "== 4. Rollback drill: restore the backup, old release runs"
mysql_ -e "DROP DATABASE $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql_ "$DB" < "$WORK/backup.sql"
( cd "$OLD_DIR" && APP_ENV=production $PHP artisan migrate:status > "$WORK/old-status.txt" && echo "old release pending migrations: $(grep -ci pending "$WORK/old-status.txt" || true)" )
( cd "$OLD_DIR" && APP_ENV=production $PHP "$NEW_DIR/tools/upgrade-rehearsal/snapshot.php" "$OLD_DIR" > "$WORK/restored.json" )
fp() { php -r '$a=json_decode(file_get_contents($argv[1]),true); if(!is_array($a)){fwrite(STDERR,"invalid snapshot\n"); exit(1);} echo json_encode([$a["counts"],$a["fingerprints"]]);' "$1"; }
if [ "$(fp "$WORK/before.json")" = "$(fp "$WORK/restored.json")" ]; then
  echo "restored database identical to the pre-upgrade snapshot: yes"
else
  echo "restored database identical to the pre-upgrade snapshot: NO"; exit 1
fi
git -C "$NEW_DIR" worktree remove --force "$OLD_DIR"
echo "== Rehearsal $LABEL finished"
