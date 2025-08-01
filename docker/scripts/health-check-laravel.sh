#!/bin/bash

# Laravel 容器健康檢查腳本
# 檢查 PHP 和基本檔案系統是否正常

set -e

# 檢查 PHP 是否可用
if ! php -v > /dev/null 2>&1; then
    echo "PHP 不可用"
    exit 1
fi

# 檢查 Laravel 基本檔案是否存在
if [ ! -f "/var/www/html/artisan" ]; then
    echo "Laravel artisan 檔案不存在"
    exit 1
fi

# 檢查 storage 目錄是否可寫
if [ ! -w "/var/www/html/storage" ]; then
    echo "storage 目錄不可寫"
    exit 1
fi

# 檢查 .env 檔案是否存在
if [ ! -f "/var/www/html/.env" ]; then
    echo ".env 檔案不存在"
    exit 1
fi

echo "Laravel 容器健康檢查通過"
exit 0