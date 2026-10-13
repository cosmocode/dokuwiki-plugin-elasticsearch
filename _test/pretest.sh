#!/usr/bin/env bash
#
# Starts the Apache Tika service from docker-compose.yml and waits until it is ready.
#
# This is run automatically by the DokuWiki GitHub workflow before PHPUnit is executed.
# It can also be run manually on a dev machine before running the tests.

set -euo pipefail

cd "$(dirname "$0")/.."

TIKA_STATUS_URL="http://localhost:9998/tika"
TIMEOUT=180

echo "Starting Tika container..."
docker compose up -d tika

echo "Waiting for Tika at $TIKA_STATUS_URL ..."
start=$(date +%s)
until curl -sf -o /dev/null "$TIKA_STATUS_URL"; do
    if (( $(date +%s) - start > TIMEOUT )); then
        echo "Tika did not become ready within ${TIMEOUT}s" >&2
        docker compose logs tika >&2 || true
        exit 1
    fi
    sleep 3
done

echo "Tika is ready."
