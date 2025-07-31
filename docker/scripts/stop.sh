#!/bin/bash

# 統一API Server Docker停止腳本

set -e  # 遇到錯誤時停止執行

ENVIRONMENT=${1:-development}
REMOVE_VOLUMES=${2:-false}

echo "=== 停止統一API Server (環境: $ENVIRONMENT) ==="

# 停止容器
echo "停止Docker容器..."
if [ "$ENVIRONMENT" = "production" ]; then
    if [ "$REMOVE_VOLUMES" = "true" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml down --volumes --remove-orphans
    else
        docker compose -f docker compose.yml -f docker compose.prod.yml down --remove-orphans
    fi
else
    if [ "$REMOVE_VOLUMES" = "true" ]; then
        docker compose down --volumes --remove-orphans
    else
        docker compose down --remove-orphans
    fi
fi

# 顯示剩餘的容器（如果有）
echo "檢查剩餘的容器..."
remaining_containers=$(docker ps -a --filter "name=unified-api" --format "table {{.Names}}\t{{.Status}}")
if [ -n "$remaining_containers" ]; then
    echo "剩餘的容器："
    echo "$remaining_containers"
else
    echo "所有容器已成功停止並移除"
fi

echo "=== 統一API Server已停止 ==="

if [ "$REMOVE_VOLUMES" = "true" ]; then
    echo ""
    echo "注意: 資料庫和Redis資料已被刪除"
    echo "下次啟動時將重新初始化資料庫"
fi