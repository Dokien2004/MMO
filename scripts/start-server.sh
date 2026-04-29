#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOST="${APP_HOST:-0.0.0.0}"
PORT="${APP_PORT:-8088}"
mkdir -p "$ROOT_DIR/storage/logs"
if ss -ltnp | grep -q ":${PORT} "; then
  echo "Port ${PORT} đang được sử dụng. Nếu là app MMO thì không cần start lại."
  ss -ltnp | grep ":${PORT} " || true
  exit 0
fi
nohup php -S "${HOST}:${PORT}" -t "$ROOT_DIR/backend/public" > "$ROOT_DIR/storage/logs/server.log" 2>&1 &
echo $! > "$ROOT_DIR/storage/logs/server.pid"
echo "Started Affiliate MVP Laptop at http://${HOST}:${PORT} pid=$(cat "$ROOT_DIR/storage/logs/server.pid")"
