#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="/home/u438562206/domains/modlus.in/public_html"
LOG_FILE="${REPO_DIR}/deploy.log"

function log() {
  local level="$1"
  local message="$2"
  printf '[%s] %s: %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$level" "$message"
}

cd "$REPO_DIR" || {
  log "ERROR" "Repository directory not found: $REPO_DIR"
  exit 1
}

if ! command -v git >/dev/null 2>&1; then
  log "ERROR" "git command not found"
  exit 1
fi

if [ ! -d ".git" ]; then
  log "ERROR" "No .git directory found in $REPO_DIR"
  exit 1
fi

log "INFO" "Starting deploy in $REPO_DIR"

if [ -n "$(git status --porcelain)" ]; then
  log "ERROR" "Working tree is dirty. Commit or stash changes before deploying."
  git status --short | while IFS= read -r line; do log "ERROR" "$line"; done
  exit 1
fi

if ! git fetch origin main --quiet; then
  log "ERROR" "git fetch origin main failed"
  exit 1
fi

if ! git pull --ff-only origin main; then
  log "ERROR" "git pull origin main failed"
  exit 1
fi

log "SUCCESS" "Deploy completed successfully"
exit 0
