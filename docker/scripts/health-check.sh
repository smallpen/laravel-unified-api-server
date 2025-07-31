#!/bin/bash

# 統一API Server健康檢查腳本

set -e

ENVIRONMENT=${1:-development}

echo "=== 統一API Server健康檢查 ==="

# 檢查容器狀態
echo "檢查容器狀態..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker compose -f docker compose.yml -f docker compose.prod.yml ps
else
    docker compose ps
fi

echo ""

# 檢查服務健康狀態
check_service() {
    local service_name="$1"
    local url="$2"
    local expected_status="$3"
    
    echo -n "檢查 $service_name... "
    
    if command -v curl &> /dev/null; then
        status_code=$(curl -s -o /dev/null -w "%{http_code}" "$url" || echo "000")
        if [ "$status_code" = "$expected_status" ]; then
            echo "✓ 正常 (HTTP $status_code)"
        else
            echo "✗ 異常 (HTTP $status_code)"
            return 1
        fi
    else
        echo "⚠ 無法檢查 (curl未安裝)"
    fi
}

# 檢查各項服務
echo "檢查服務健康狀態..."

check_service "HTTP服務" "http://localhost/health" "200"
check_service "HTTPS服務" "https://localhost/health" "200" || echo "  (SSL憑證可能為自簽名)"
check_service "API端點" "http://localhost/api/" "405"  # POST方法才被允許

if [ "$ENVIRONMENT" != "production" ]; then
    check_service "PhpMyAdmin" "http://localhost:8080" "200"
    check_service "Mailpit" "http://localhost:8025" "200"
fi

echo ""

# 檢查資料庫連線
echo "檢查資料庫連線..."
if [ "$ENVIRONMENT" = "production" ]; then
    if docker compose -f docker compose.yml -f docker compose.prod.yml exec -T laravel php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';" 2>/dev/null; then
        echo "✓ 資料庫連線正常"
    else
        echo "✗ 資料庫連線失敗"
    fi
else
    if docker compose exec -T laravel php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';" 2>/dev/null; then
        echo "✓ 資料庫連線正常"
    else
        echo "✗ 資料庫連線失敗"
    fi
fi

# 檢查Redis連線
echo "檢查Redis連線..."
if [ "$ENVIRONMENT" = "production" ]; then
    if docker compose -f docker compose.yml -f docker compose.prod.yml exec -T laravel php artisan tinker --execute="Redis::ping(); echo 'Redis connection: OK';" 2>/dev/null; then
        echo "✓ Redis連線正常"
    else
        echo "✗ Redis連線失敗"
    fi
else
    if docker compose exec -T laravel php artisan tinker --execute="Redis::ping(); echo 'Redis connection: OK';" 2>/dev/null; then
        echo "✓ Redis連線正常"
    else
        echo "✗ Redis連線失敗"
    fi
fi

# 檢查日誌錯誤
echo ""
echo "檢查最近的錯誤日誌..."
if [ "$ENVIRONMENT" = "production" ]; then
    error_count=$(docker compose -f docker compose.yml -f docker compose.prod.yml exec -T laravel tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -i error | wc -l || echo "0")
else
    error_count=$(docker compose exec -T laravel tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -i error | wc -l || echo "0")
fi

if [ "$error_count" -eq 0 ]; then
    echo "✓ 最近100行日誌中無錯誤"
else
    echo "⚠ 最近100行日誌中發現 $error_count 個錯誤"
fi

echo ""
echo "=== 健康檢查完成 ==="