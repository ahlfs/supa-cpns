#!/usr/bin/env bash
# Create an isolated copy of the app so a section can be built and pixel-tested
# without touching the working tree or fighting another build for public/build.
#
#   tools/sandbox.sh <name> <port>
#
# The copy shares vendor/, node_modules/ and public/assets with the working tree
# (symlinks), so it is cheap; public/build is its own.
set -euo pipefail

NAME="${1:?usage: tools/sandbox.sh <name> <port>}"
PORT="${2:?usage: tools/sandbox.sh <name> <port>}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="/home/ahlfs/workspace/.sandboxes/${NAME}"

rm -rf "${DEST}"
mkdir -p "${DEST}"

# Source tree, minus the heavy shared directories.
tar -C "${ROOT}" -cf - \
  --exclude=node_modules --exclude=vendor --exclude=public/assets --exclude=public/build \
  --exclude=storage/app/parity --exclude=.git . | tar -C "${DEST}" -xf -

ln -s "${ROOT}/node_modules" "${DEST}/node_modules"
ln -s "${ROOT}/vendor" "${DEST}/vendor"
mkdir -p "${DEST}/public"
ln -s "${ROOT}/public/assets" "${DEST}/public/assets"

mkdir -p "${DEST}/storage/framework/"{cache,sessions,views} "${DEST}/storage/logs" "${DEST}/bootstrap/cache"

# Its own build output and URLs.
mkdir -p "${DEST}/public/build"
cp "${ROOT}/.env" "${DEST}/.env" 2>/dev/null || true
sed -i "s|^APP_URL=.*|APP_URL=http://127.0.0.1:${PORT}|" "${DEST}/.env"

echo "sandbox ready: ${DEST}"
echo "  cd ${DEST} && npm run build"
echo "  cd ${DEST} && php artisan serve --host=127.0.0.1 --port=${PORT}"
echo "  cd ${DEST} && node tools/page-shots.mjs http://127.0.0.1:${PORT}"
