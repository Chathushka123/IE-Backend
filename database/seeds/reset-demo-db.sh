#!/usr/bin/env bash
# Rebuilds the demo database from the clean, migrated copy (ie_uat_pristine), then fills it.
#   database/seeds/reset-demo-db.sh            -> drop ie_uat, copy ie_uat_pristine into it, run demo:seed
# Never touches ie_uat_pristine or any *_test database. Needs MySQL credentials in .env (DB_USERNAME / DB_PASSWORD).
set -euo pipefail
cd "$(dirname "$0")/../.."
export MYSQL_PWD="$(grep ^DB_PASSWORD .env | cut -d= -f2-)"
USER_NAME="$(grep ^DB_USERNAME .env | cut -d= -f2-)"
TARGET="${1:-ie_uat}"
case "$TARGET" in *_test|ie_uat_pristine) echo "Refusing to reset '$TARGET'." >&2; exit 1;; esac
mysql -u "$USER_NAME" -e "drop database if exists \`$TARGET\`; create database \`$TARGET\` character set utf8mb4 collate utf8mb4_unicode_ci"
mysqldump -u "$USER_NAME" --single-transaction --routines --triggers ie_uat_pristine | mysql -u "$USER_NAME" "$TARGET"
# APP_CONFIG_CACHE points at a file that does not exist, so a cached config cannot redirect this to another database.
DB_DATABASE="$TARGET" APP_CONFIG_CACHE=/nonexistent/config.php php artisan demo:seed --database="$TARGET" --studies="${STUDIES:-1000}"
