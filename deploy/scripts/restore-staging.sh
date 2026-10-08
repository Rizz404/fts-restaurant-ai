#!/usr/bin/env bash
# FTS Restaurant AI — restore a backup into a STAGING or TESTING database only
# (PLAN_08). The target must be named explicitly and end with _staging or
# _test; production databases are refused.
#
# Usage:
#   RESTORE_TARGET_DB=restaurant_ai_staging deploy/scripts/restore-staging.sh /var/backups/restaurant-ai/restaurant_ai-20261005T011500Z.dump
#
# Optional: RESTORE_PHOTOS_ARCHIVE=<file.tar.gz> and RESTORE_PHOTOS_PARENT=<staging
# storage/app/public directory> restore the menu photos next to the database.
#
# Credentials come from PGHOST/PGPORT/PGUSER and PGPASSFILE (0600) or peer
# authentication, never from the command line.
set -euo pipefail

dump="${1:?Usage: RESTORE_TARGET_DB=<name>_staging $0 <file.dump>}"
target="${RESTORE_TARGET_DB:?Set RESTORE_TARGET_DB explicitly}"

case "$target" in
    *_staging|*_test) ;;
    *) echo "Refusing to restore into '$target': name must end with _staging or _test." >&2; exit 1 ;;
esac

if [[ -n "${PGPASSWORD:-}" ]]; then
    echo "Refusing to run with PGPASSWORD set; use PGPASSFILE or peer authentication." >&2
    exit 1
fi

if [[ -f "$dump.sha256" ]]; then
    (cd "$(dirname "$dump")" && sha256sum --check --status "$(basename "$dump").sha256") \
        || { echo "Checksum mismatch for $dump" >&2; exit 1; }
fi

echo "Restoring $(basename "$dump") into $target (existing objects are replaced)…"
pg_restore --clean --if-exists --no-owner --no-privileges --single-transaction --dbname="$target" "$dump"
if [[ -n "${RESTORE_PHOTOS_ARCHIVE:-}" ]]; then
    parent="${RESTORE_PHOTOS_PARENT:?Set RESTORE_PHOTOS_PARENT with RESTORE_PHOTOS_ARCHIVE}"
    case "$parent" in
        *staging*|*test*) ;;
        *) echo "Refusing to restore photos into '$parent': the path must contain 'staging' or 'test'." >&2; exit 1 ;;
    esac
    if [[ -f "$RESTORE_PHOTOS_ARCHIVE.sha256" ]]; then
        (cd "$(dirname "$RESTORE_PHOTOS_ARCHIVE")" && sha256sum --check --status "$(basename "$RESTORE_PHOTOS_ARCHIVE").sha256")             || { echo "Checksum mismatch for $RESTORE_PHOTOS_ARCHIVE" >&2; exit 1; }
    fi
    mkdir -p "$parent"
    tar -C "$parent" -xzf "$RESTORE_PHOTOS_ARCHIVE"
    echo "Menu photos restored into $parent/menu."
fi

echo "Restore finished. Next: php artisan migrate --force against $target, then repeat the staging journey (docs/OPERATIONS.md)."
