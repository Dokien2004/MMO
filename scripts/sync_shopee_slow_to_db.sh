#!/usr/bin/env bash
set -u
cd "$(dirname "$0")/.."
SOURCE="${SHOPEE_SLOW_OUTPUT:-storage/data/sources/shopee_slow_sales_products.json}"
LOG="${SHOPEE_SLOW_SYNC_LOG:-storage/logs/shopee_slow_db_sync.log}"
INTERVAL="${SHOPEE_SLOW_SYNC_INTERVAL:-300}"
mkdir -p "$(dirname "$LOG")"
echo "$(date -Is) sync loop started source=$SOURCE interval=${INTERVAL}s" >> "$LOG"
while true; do
  if [ -s "$SOURCE" ]; then
    php workers/sync_sample_products.php shopee "$SOURCE" >> "$LOG" 2>&1
  else
    echo "$(date -Is) source missing/empty: $SOURCE" >> "$LOG"
  fi
  sleep "$INTERVAL"
done
