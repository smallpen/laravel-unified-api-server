#!/bin/bash

echo "=== Laravel Composer 診斷腳本 ==="

# 檢查本地檔案
echo "1. 檢查本地 composer 檔案..."
if [ -f "composer.json" ]; then
    echo "✓ composer.json 存在"
else
    echo "✗ composer.json 不存在"
fi

if [ -f "composer.lock" ]; then
    echo "✓ composer.lock 存在"
else
    echo "✗ composer.lock 不存在"
fi

# 檢查容器內的檔案
echo ""
echo "2. 檢查容器內的檔案結構..."
docker-compose run --rm laravel ls -la /var/www/html/

echo ""
echo "3. 檢查容器內的 vendor 目錄..."
if docker-compose run --rm laravel test -d /var/www/html/vendor; then
    echo "✓ vendor 目錄存在"
    docker-compose run --rm laravel ls -la /var/www/html/vendor/ | head -10
else
    echo "✗ vendor 目錄不存在"
fi

echo ""
echo "4. 檢查 autoload.php 檔案..."
if docker-compose run --rm laravel test -f /var/www/html/vendor/autoload.php; then
    echo "✓ vendor/autoload.php 存在"
else
    echo "✗ vendor/autoload.php 不存在"
fi

echo ""
echo "5. 檢查 Composer 版本..."
docker-compose run --rm laravel composer --version

echo ""
echo "6. 檢查 PHP 版本..."
docker-compose run --rm laravel php --version

echo ""
echo "=== 診斷完成 ==="