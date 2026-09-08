#!/usr/bin/env bash
# Trigger RelayIQ's existing cPanel deploy endpoint and fail if the stream reports an error.
set -euo pipefail

REMOTE_URL="${DEPLOY_REMOTE_URL:-https://relayiq.app}"
DEPLOY_KEY="${DEPLOY_AGENT_KEY:?DEPLOY_AGENT_KEY GitHub secret is required}"
BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE_URL="${REMOTE_URL%/}"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

http_code=""
for attempt in 1 2 3 4 5 6; do
  echo "Triggering deploy of [${BRANCH}] on ${REMOTE_URL} (attempt ${attempt}/6)..."
  http_code="$(
    curl -N -sS -o "$tmp" -w "%{http_code}" \
      -X POST "${REMOTE_URL}/deploy/agent" \
      -H "X-Deploy-Agent-Key: ${DEPLOY_KEY}" \
      -H "Content-Type: application/json" \
      -H "Accept: text/event-stream" \
      --max-time 600 \
      -d "{\"branch\": \"${BRANCH}\"}"
  )" || true

  cat "$tmp"
  echo

  if [ "$http_code" = "409" ]; then
    echo "Deploy lock is held. Waiting 20s..."
    sleep 20
    continue
  fi

  break
done

if [ "$http_code" != "200" ]; then
  echo "::error::Deploy endpoint returned HTTP ${http_code}"
  exit 1
fi

if grep -Eq '"type"[[:space:]]*:[[:space:]]*"error"' "$tmp" || grep -Eq '"success"[[:space:]]*:[[:space:]]*false' "$tmp"; then
  echo "::error::Server reported a failed deployment"
  exit 1
fi

if ! grep -Eq '"type"[[:space:]]*:[[:space:]]*"done"' "$tmp"; then
  echo "::error::Deploy stream ended without a done event"
  exit 1
fi

echo "Agent deployment stream completed successfully."
