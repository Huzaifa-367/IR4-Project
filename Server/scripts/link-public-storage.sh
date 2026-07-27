#!/usr/bin/env sh
# Create public/storage -> storage/app/public without PHP symlink()/exec()
# (shared hosts often disable those; Laravel's artisan storage:link can then fail).
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/public" || exit 1
if [ ! -d "$ROOT/storage/app/public" ]; then
    echo "Expected directory missing: $ROOT/storage/app/public" >&2
    exit 1
fi
rm -f storage
ln -sfn ../storage/app/public storage
echo "Linked: $ROOT/public/storage -> ../storage/app/public"
