#!/usr/bin/env bash
# Reviewed preparation only. Install/configure on the VPS after authorization.
set -euo pipefail
umask 077

[[ $(id -u) -eq 0 ]] || { echo 'Run with the dedicated root backup configuration.' >&2; exit 1; }
config=/etc/wholesale-backup/restic.env
mysql_config=/etc/wholesale-backup/mysql.cnf
for private_file in "$config" "$mysql_config"; do
    [[ -f "$private_file" && $(stat -c %u "$private_file") == 0 && $(stat -c %a "$private_file") == 600 ]] || {
        echo 'Backup configuration must exist, be root-owned and mode600.' >&2; exit 1;
    }
done
set -a
# This is trusted root-owned shell configuration; never source a user upload.
source "$config"
set +a
: "${RESTIC_REPOSITORY:?required}" "${RESTIC_PASSWORD_FILE:?required}" "${BACKUP_DATABASE:?required}"
[[ "$RESTIC_REPOSITORY" == s3:https://* && "$RESTIC_REPOSITORY" != *__* ]] || {
    echo 'Configure a real off-server HTTPS S3 repository.' >&2; exit 1;
}
[[ "$BACKUP_DATABASE" =~ ^[a-zA-Z0-9_]+$ && "$BACKUP_DATABASE" != *__* ]] || exit 1
[[ -f "$RESTIC_PASSWORD_FILE" && $(stat -c %u "$RESTIC_PASSWORD_FILE") == 0 && $(stat -c %a "$RESTIC_PASSWORD_FILE") == 600 ]] || {
    echo 'Repository password file must be root-owned and mode600.' >&2; exit 1;
}
storage=/var/www/wholesale-order-system/shared/storage
[[ -d "$storage" ]] || { echo 'Shared storage is missing.' >&2; exit 1; }
for tool in mariadb-dump gzip restic flock; do command -v "$tool" >/dev/null; done
exec 9>/run/lock/wholesale-backup.lock
flock -n 9 || { echo 'Another backup is running.' >&2; exit 1; }
stage=$(mktemp -d /var/backups/wholesale-stage.XXXXXXXX)
cleanup() {
    case "$stage" in /var/backups/wholesale-stage.*) rm -rf -- "$stage" ;; *) exit 1 ;; esac
}
trap cleanup EXIT

mariadb-dump --defaults-extra-file="$mysql_config" --single-transaction --quick --routines --triggers --events "$BACKUP_DATABASE" | gzip > "$stage/database.sql.gz"
gzip -t "$stage/database.sql.gz"
restic backup --tag wholesale-production "$stage/database.sql.gz" "$storage"
# Retention is applied only after the encrypted remote backup has succeeded.
restic forget --tag wholesale-production --group-by host,tags --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune
restic check
echo 'Encrypted off-server backup and repository check completed.'
