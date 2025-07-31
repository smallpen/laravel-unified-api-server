#!/bin/bash

# 設定錯誤處理
set -e

echo "正在啟動 Laravel 應用程式..."

# 等待資料庫連線
echo "等待資料庫連線..."
max_attempts=30
attempt=0
until php artisan migrate:status > /dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ $attempt -gt $max_attempts ]; then
        echo "資料庫連線逾時，但繼續啟動服務..."
        break
    fi
    echo "等待資料庫連線... (嘗試 $attempt/$max_attempts)"
    sleep 2
done

# 執行資料庫遷移（如果資料庫可用）
if php artisan migrate:status > /dev/null 2>&1; then
    echo "執行資料庫遷移..."
    php artisan migrate --force
else
    echo "跳過資料庫遷移（資料庫不可用）"
fi

# 確保儲存目錄權限正確
echo "設定儲存目錄權限..."
chown -R www:www /var/www/html/storage
chown -R www:www /var/www/html/bootstrap/cache
chown -R www:www /var/log/supervisor
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
chmod -R 755 /var/log/supervisor

# 清除快取
echo "清除應用程式快取..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 重新建立快取
echo "重新建立快取..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Laravel 應用程式啟動完成！"

# 啟動 supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf