#!/usr/bin/env bash
# FTS Restaurant AI — PostgreSQL backup (custom format) (PLAN_08).
#
# Credentials come from the environment / service config, never from the
# command line or this file:
#   PGHOST, PGPORT, PGDATABASE, PGUSER, and PGPASSFILE (a 0600 .pgpass) or a
#   peer/cert-authenticated service user.
#   BACKUP_DIR   private directory (not under public/), e.g. /var/backups/restaurant-ai
#   BACKUP_KEEP  number of dumps to keep (default 14)
#   PHOTOS_DIR   menu photo directory to archive with the same schedule, e.g.
#                /var/www/restaurant-ai/shared/storage/app/public/menu (skipped when unset)
#
# Example (cron, as the backup user):
#   15 1 * * * BACKUP_DIR=/var/backups/restaurant-ai /var/www/restaurant-ai/current/deploy/scripts/backup.sh
set -euo pipefail
umask 077

: "${PGDATABASE:?Set PGDATABASE}"
: "${BACKUP_DIR:?Set BACKUP_DIR}"
KEEP="${BACKUP_KEEP:-14}"

if [[ -n "${PGPASSWORD:-}" ]]; then
    echo "Refusing to run with PGPASSWORD set; use PGPASSFILE or peer authentication." >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="$BACKUP_DIR/${PGDATABASE}-${stamp}.dump"
partial="$target.partial"

pg_dump --format=custom --no-owner --no-privileges --file="$partial" "$PGDATABASE"
pg_restore --list "$partial" > /dev/null   # verify the archive is readable
mv "$partial" "$target"
sha256sum "$target" > "$target.sha256"

# Keep the newest $KEEP dumps.
ls -1t "$BACKUP_DIR"/"${PGDATABASE}"-*.dump 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do
    rm -f -- "$old" "$old.sha256"
done

# Menu photos are part of the data: archive them next to the dump.
if [[ -n "${PHOTOS_DIR:-}" ]]; then
    if [[ -d "$PHOTOS_DIR" ]]; then
        photos="$BACKUP_DIR/${PGDATABASE}-photos-${stamp}.tar.gz"
        tar -C "$(dirname "$PHOTOS_DIR")" -czf "$photos.partial" "$(basename "$PHOTOS_DIR")"
        mv "$photos.partial" "$photos"
        sha256sum "$photos" > "$photos.sha256"
        ls -1t "$BACKUP_DIR"/"${PGDATABASE}"-photos-*.tar.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do
            rm -f -- "$old" "$old.sha256"
        done
        echo "Photos archived: $(basename "$photos")"
    else
        echo "PHOTOS_DIR '$PHOTOS_DIR' is not a directory; photos were not archived." >&2
        exit 1
    fi
fi

echo "Backup written: $(basename "$target")"
