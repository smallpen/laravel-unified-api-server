#!/bin/bash

# 佇列狀態檢查腳本

echo "=== Laravel 佇列狀態檢查 ==="
echo "時間: $(date)"
echo ""

# 檢查 Redis 連線
echo "1. 檢查 Redis 連線狀態..."
docker exec unified-api-laravel php artisan tinker --execute="
try {
    \$redis = app('redis');
    \$redis->ping();
    echo 'Redis 連線正常\n';
} catch (Exception \$e) {
    echo 'Redis 連線失敗: ' . \$e->getMessage() . '\n';
}
"

echo ""

# 檢查佇列配置
echo "2. 檢查佇列配置..."
docker exec unified-api-laravel php artisan config:show queue

echo ""

# 檢查佇列任務數量
echo "3. 檢查佇列任務數量..."
docker exec unified-api-laravel php artisan queue:monitor

echo ""

# 檢查 supervisor 狀態
echo "4. 檢查 supervisor 進程狀態..."
docker exec unified-api-laravel supervisorctl status

echo ""

# 檢查 worker 日誌
echo "5. 最近的 worker 日誌..."
docker exec unified-api-laravel tail -20 /var/log/supervisor/worker.log

echo ""

# 檢查錯誤日誌
echo "6. 最近的錯誤日誌..."
docker exec unified-api-laravel tail -20 /var/log/supervisor/worker_error.log 2>/dev/null || echo "沒有錯誤日誌檔案"

echo ""
echo "=== 檢查完成 ==="