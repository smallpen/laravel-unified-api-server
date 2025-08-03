#!/bin/bash

# Docker 建置驗證腳本
# 用於檢查 Laravel Docker 容器的建置過程

echo "=== Laravel Docker 建置驗證腳本 ==="
echo ""

# 檢查必要檔案是否存在
echo "1. 檢查必要檔案..."
required_files=("Dockerfile" "composer.json" "composer.lock" ".env.example")

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file 存在"
    else
        echo "✗ $file 不存在"
        exit 1
    fi
done

echo ""

# 建置 Docker 映像檔
echo "2. 建置 Docker 映像檔..."
docker build -t laravel-app-test . --no-cache

if [ $? -eq 0 ]; then
    echo "✓ Docker 映像檔建置成功"
else
    echo "✗ Docker 映像檔建置失敗"
    exit 1
fi

echo ""

# 檢查 vendor 目錄是否存在
echo "3. 檢查 composer 依賴是否正確安裝..."
docker run --rm laravel-app-test ls -la /var/www/html/vendor > /tmp/vendor_check.txt

if grep -q "autoload.php" /tmp/vendor_check.txt; then
    echo "✓ Composer 依賴已正確安裝"
    echo "✓ autoload.php 檔案存在"
else
    echo "✗ Composer 依賴安裝有問題"
    echo "Vendor 目錄內容："
    cat /tmp/vendor_check.txt
    exit 1
fi

echo ""

# 檢查 Laravel 核心檔案
echo "4. 檢查 Laravel 核心檔案..."
docker run --rm laravel-app-test ls -la /var/www/html/vendor/laravel/framework > /tmp/laravel_check.txt

if grep -q "src" /tmp/laravel_check.txt; then
    echo "✓ Laravel 框架檔案存在"
else
    echo "✗ Laravel 框架檔案不存在"
    cat /tmp/laravel_check.txt
    exit 1
fi

echo ""

# 測試 PHP 和 Composer 版本
echo "5. 檢查 PHP 和 Composer 版本..."
echo "PHP 版本："
docker run --rm laravel-app-test php --version

echo ""
echo "Composer 版本："
docker run --rm laravel-app-test composer --version

echo ""

# 檢查 Laravel artisan 命令
echo "6. 測試 Laravel artisan 命令..."
docker run --rm laravel-app-test php artisan --version

if [ $? -eq 0 ]; then
    echo "✓ Laravel artisan 命令正常運作"
else
    echo "✗ Laravel artisan 命令執行失敗"
    exit 1
fi

echo ""

# 清理測試映像檔
echo "7. 清理測試映像檔..."
docker rmi laravel-app-test

echo ""
echo "=== 建置驗證完成 ==="
echo "✓ 所有檢查都通過，Docker 映像檔建置正常"