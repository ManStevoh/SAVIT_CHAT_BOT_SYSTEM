#!/usr/bin/env bash
# Roll production back to a previous deploy-* git tag, then re-run post-deploy.
# Usage: bash scripts/rollback.sh [deploy-tag]
set -euo pipefail

cd "$(dirname "$0")/.."

repo_root=".."
if [ ! -d "${repo_root}/.git" ] && [ -d .git ]; then
  repo_root="."
fi

git -C "$repo_root" fetch --tags origin

tag="${1:-}"
if [ -z "$tag" ]; then
  tag="$(git -C "$repo_root" tag --list 'deploy-*' --sort=-creatordate | sed -n '2p')"
fi

if [ -z "$tag" ]; then
  echo "ERROR: No previous deploy-* tag found. Pass a tag explicitly."
  echo "Available tags:"
  git -C "$repo_root" tag --list 'deploy-*' --sort=-creatordate | sed -n '1,20p'
  exit 1
fi

echo "==> Rolling back to ${tag}..."
git -C "$repo_root" checkout --force "$tag"

bash scripts/write-release-version.sh
bash scripts/post-deploy.sh

echo "==> Rollback to ${tag} complete. Verify: curl -fsS https://relayiq.app/api/health"
