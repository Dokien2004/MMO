#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$ROOT_DIR/storage/logs/server.pid"
if [ ! -f "$PID_FILE" ]; then
  echo "Không thấy PID file: $PID_FILE"
  exit 0
fi
PID="$(cat "$PID_FILE")"
if kill -0 "$PID" 2>/dev/null; then
  kill "$PID"
  echo "Stopped pid=$PID"
else
  echo "Process pid=$PID không còn chạy"
fi
rm -f "$PID_FILE"
