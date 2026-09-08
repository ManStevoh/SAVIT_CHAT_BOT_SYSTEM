#!/usr/bin/env bash
# Confirm RelayIQ is serving traffic after deploy.
# Prefers GET /api/health JSON; falls back to Laravel's GET /up during the first rollout.
set -euo pipefail

BASE_URL="${1:-https://relayiq.app}"
BASE_URL="${BASE_URL%/}"

for attempt in 1 2 3 4 5 6; do
  echo "Health check ${BASE_URL} (attempt ${attempt}/6)..."

  if body="$(curl -fsS --max-time 20 "${BASE_URL}/api/health" 2>/dev/null)"; then
    echo "$body"
    if printf '%s' "$body" | python3 -c 'import json,sys; data=json.load(sys.stdin); sys.exit(0 if data.get("status")=="ok" else 1)'; then
      echo "Health check passed via /api/health"
      exit 0
    fi
    echo " /api/health responded but status was not ok"
  elif curl -fsS --max-time 20 "${BASE_URL}/up" >/dev/null 2>&1; then
    echo "Health check passed via legacy /up"
    exit 0
  fi

  echo "Waiting 10s for the app to respond..."
  sleep 10
done

echo "::error::Health check failed for ${BASE_URL}"
exit 1
