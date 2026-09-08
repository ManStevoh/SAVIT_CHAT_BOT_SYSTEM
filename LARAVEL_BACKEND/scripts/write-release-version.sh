#!/usr/bin/env bash
# Write LARAVEL_BACKEND/VERSION from the current git SHA (used by GET /api/health).
set -euo pipefail

cd "$(dirname "$0")/.."

sha="unknown"
if git -C .. rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  sha="$(git -C .. rev-parse --short HEAD)"
elif git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  sha="$(git rev-parse --short HEAD)"
fi

printf '%s.%s\n' "$(date -u +%Y.%m.%d)" "$sha" > VERSION
echo "==> Release version: $(tr -d '\n' < VERSION)"
