#!/bin/bash

echo "=== Laravel Composer 修復腳本 ==="

# 停止並移除現有容器
echo "停止現有容器..."
docker compose down

# 清理 Docker 快取
echo "清理 Docker 建置快取..."
docker system prune -f
docker builder prune -f

# 重新建置容器（不使用快取）
echo "重新建置容器..."
docker compose build --no-cache laravel

# 檢查 vendor 目錄是否存在
echo "檢查容器內的 vendor 目錄..."
docker compose run --rm laravel ls -la /var/www/html/vendor

# 如果 vendor 目錄不存在，手動安裝 Composer 依賴
if ! docker compose run --rm laravel test -d /var/www/html/vendor; then
    echo "vendor 目錄不存在，手動安裝 Composer 依賴..."
    docker compose run --rm laravel composer install --no-dev --optimize-autoloader
fi

# 啟動服務
echo "啟動服務..."
docker compose up -d

echo "=== 修復完成 ==="