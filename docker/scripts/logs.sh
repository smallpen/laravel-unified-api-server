#!/bin/bash

# 統一API Server日誌查看腳本

ENVIRONMENT=${1:-development}
SERVICE=${2:-all}
LINES=${3:-100}

echo "=== 統一API Server日誌查看 ==="
echo "環境: $ENVIRONMENT"
echo "服務: $SERVICE"
echo "行數: $LINES"
echo ""

if [ "$SERVICE" = "all" ]; then
    echo "顯示所有服務日誌..."
    if [ "$ENVIRONMENT" = "production" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml logs --tail="$LINES" -f
    else
        docker compose logs --tail="$LINES" -f
    fi
else
    echo "顯示 $SERVICE 服務日誌..."
    if [ "$ENVIRONMENT" = "production" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml logs --tail="$LINES" -f "$SERVICE"
    else
        docker compose logs --tail="$LINES" -f "$SERVICE"
    fi
fi