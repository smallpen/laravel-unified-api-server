#!/bin/bash

# 統一API Server Docker啟動腳本

set -e  # 遇到錯誤時停止執行

ENVIRONMENT=${1:-development}

echo "=== 啟動統一API Server (環境: $ENVIRONMENT) ==="

# 執行環境設定腳本
echo "設定環境變數..."
./docker/scripts/setup-env.sh

# 生成SSL憑證（如果不存在）
if [ ! -f "./docker/nginx/ssl/server.crt" ]; then
    echo "生成SSL憑證..."
    ./docker/scripts/generate-ssl.sh
fi

# 建立必要的目錄
echo "建立必要的目錄..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
mkdir -p docker/nginx/ssl

# 設定權限
echo "設定目錄權限..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod +x docker/scripts/*.sh

# 啟動Docker容器
echo "啟動Docker容器..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
else
    docker-compose up -d
fi

# 等待服務啟動
echo "等待服務啟動..."
sleep 45

# 檢查服務健康狀態
echo "檢查服務健康狀態..."
max_attempts=30
attempt=1

while [ $attempt -le $max_attempts ]; do
    if [ "$ENVIRONMENT" = "production" ]; then
        if docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan tinker --execute="echo 'OK';" > /dev/null 2>&1; then
            break
        fi
    else
        if docker-compose exec laravel php artisan tinker --execute="echo 'OK';" > /dev/null 2>&1; then
            break
        fi
    fi
    
    echo "等待Laravel服務啟動... (嘗試 $attempt/$max_attempts)"
    sleep 2
    attempt=$((attempt + 1))
done

if [ $attempt -gt $max_attempts ]; then
    echo "錯誤: Laravel服務啟動失敗"
    exit 1
fi

# 安裝Composer依賴
echo "安裝Composer依賴..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel composer install --no-dev --optimize-autoloader --no-interaction
else
    docker-compose exec laravel composer install --optimize-autoloader --no-interaction
fi

# 產生應用程式金鑰（如果尚未設定）
echo "檢查應用程式金鑰..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan key:generate --no-interaction
else
    docker-compose exec laravel php artisan key:generate --no-interaction
fi

# 執行資料庫遷移
echo "執行資料庫遷移..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan migrate --force --no-interaction
else
    docker-compose exec laravel php artisan migrate --no-interaction
    docker-compose exec laravel php artisan db:seed --no-interaction
fi

# 清除和建立快取
echo "建立快取..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan config:cache
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan route:cache
    docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec laravel php artisan view:cache
else
    docker-compose exec laravel php artisan config:cache
    docker-compose exec laravel php artisan route:cache
    docker-compose exec laravel php artisan view:cache
fi

echo "=== 統一API Server啟動完成！ ==="
echo ""
echo "服務存取資訊："
echo "- HTTP: http://localhost"
echo "- HTTPS: https://localhost"
echo "- API端點: http://localhost/api/"
echo "- API文件: http://localhost/docs"
echo "- 健康檢查: http://localhost/health"

if [ "$ENVIRONMENT" != "production" ]; then
    echo "- PhpMyAdmin: http://localhost:8080"
    echo "- Mailpit: http://localhost:8025"
fi

echo ""
echo "日誌查看："
if [ "$ENVIRONMENT" = "production" ]; then
    echo "docker-compose -f docker-compose.yml -f docker-compose.prod.yml logs -f"
else
    echo "docker-compose logs -f"
fi