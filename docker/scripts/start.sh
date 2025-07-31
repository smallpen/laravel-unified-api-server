#!/bin/bash

# 設定錯誤處理
set -e

echo "正在啟動 Laravel 應用程式..."

# 重新設定環境變數為生產環境（覆蓋建置時的設定）
export APP_ENV=${APP_ENV:-production}
echo "設定應用程式環境為: $APP_ENV"

# 檢查 vendor 目錄是否存在
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "錯誤：vendor 目錄或 autoload.php 檔案不存在！"
    echo "正在重新安裝 Composer 依賴..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
        echo "致命錯誤：無法建立 vendor/autoload.php 檔案"
        exit 1
    fi
else
    echo "vendor 目錄檢查通過"
fi

# 檢查並產生 APP_KEY（如果不存在或為空）
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "產生應用程式金鑰..."
    php artisan key:generate --force
else
    echo "應用程式金鑰已存在"
fi

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

# 確保必要的目錄結構存在
echo "檢查並建立必要的目錄結構..."
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# 確保儲存目錄權限正確
echo "設定儲存目錄權限..."
chown -R www:www /var/www/html/storage
chown -R www:www /var/www/html/bootstrap/cache
chown -R www:www /var/log/supervisor
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
chmod -R 755 /var/log/supervisor

# 測試服務綁定
echo "測試服務綁定..."
if php /var/www/html/test-binding.php; then
    echo "✓ 服務綁定測試通過"
    
    # 執行套件發現
    echo "執行套件發現..."
    php artisan package:discover --ansi
else
    echo "✗ 服務綁定測試失敗，跳過套件發現"
fi

# 清除快取
echo "清除應用程式快取..."
php artisan config:clear || echo "配置清除失敗，繼續執行..."
php artisan route:clear || echo "路由清除失敗，繼續執行..."

# 跳過視圖快取清除（API 專案通常不需要視圖）
echo "跳過視圖快取清除（API 專案）"

# 手動清除快取檔案
echo "手動清除快取檔案..."
rm -rf /var/www/html/storage/framework/cache/data/* || true
rm -rf /var/www/html/storage/framework/views/* || true
rm -rf /var/www/html/bootstrap/cache/*.php || true

# 重新建立快取
echo "重新建立快取..."
php artisan config:cache || echo "配置快取建立失敗，繼續執行..."

echo "Laravel 應用程式啟動完成！"

# 啟動 supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf