#!/bin/bash

# 佇列監控腳本

echo "=== Laravel 佇列即時監控 ==="
echo "按 Ctrl+C 停止監控"
echo ""

# 建立測試任務
echo "建立測試任務..."
docker exec unified-api-laravel php artisan queue:test --count=3

echo ""
echo "開始監控佇列處理..."
echo ""

# 即時監控佇列處理
while true; do
    clear
    echo "=== 佇列狀態監控 - $(date) ==="
    echo ""
    
    # 顯示 supervisor 狀態
    echo "Supervisor 進程狀態:"
    docker exec unified-api-laravel supervisorctl status | grep laravel-worker
    echo ""
    
    # 顯示佇列大小
    echo "佇列任務數量:"
    docker exec unified-api-laravel php artisan tinker --execute="
    try {
        echo 'Redis 佇列大小: ' . app('redis')->llen('queues:default') . PHP_EOL;
    } catch (Exception \$e) {
        echo '無法取得佇列大小: ' . \$e->getMessage() . PHP_EOL;
    }
    "
    echo ""
    
    # 顯示最新的 worker 日誌
    echo "最新 Worker 日誌 (最後 5 行):"
    docker exec unified-api-laravel tail -5 /var/log/supervisor/worker.log 2>/dev/null || echo "沒有日誌檔案"
    echo ""
    
    # 顯示最新的錯誤日誌
    echo "最新錯誤日誌 (最後 3 行):"
    docker exec unified-api-laravel tail -3 /var/log/supervisor/worker_error.log 2>/dev/null || echo "沒有錯誤日誌"
    echo ""
    
    echo "等待 5 秒後重新整理..."
    sleep 5
done