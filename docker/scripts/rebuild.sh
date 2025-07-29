#!/bin/bash

# 統一API Server Docker重建腳本

set -e  # 遇到錯誤時停止執行

ENVIRONMENT=${1:-development}

echo "=== 重建統一API Server (環境: $ENVIRONMENT) ==="

# 停止並移除現有容器
echo "停止並移除現有容器..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml down --remove-orphans
else
    docker-compose down --remove-orphans
fi

# 清理未使用的映像和卷
echo "清理未使用的Docker資源..."
docker system prune -f

# 重建Docker映像
echo "重建Docker映像..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml build --no-cache
else
    docker-compose build --no-cache
fi

# 啟動容器
echo "啟動容器..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
else
    docker-compose up -d
fi

# 等待服務啟動
echo "等待服務啟動..."
sleep 30

# 檢查容器狀態
echo "檢查容器狀態..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml ps
else
    docker-compose ps
fi

# 執行Laravel初始化命令
echo "執行Laravel初始化..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan migrate --force
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan config:cache
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan route:cache
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan view:cache
else
    docker-compose exec laravel php artisan migrate
    docker-compose exec laravel php artisan db:seed
fi

echo "=== 統一API Server重建完成！ ==="
echo ""
echo "服務存取資訊："
echo "- HTTP: http://localhost"
echo "- HTTPS: https://localhost"
if [ "$ENVIRONMENT" != "production" ]; then
    echo "- PhpMyAdmin: http://localhost:8080"
    echo "- Mailpit: http://localhost:8025"
fi