#!/bin/bash

# Composer 除錯腳本
# 用於檢查 Docker 建置過程中的 composer 問題

echo "=== Composer 除錯腳本 ==="
echo ""

# 建立除錯用的 Dockerfile
cat > Dockerfile.debug << 'EOF'
# 除錯用的 Dockerfile
FROM php:8.1-fpm

WORKDIR /var/www/html

# 安裝系統依賴
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 複製 composer 檔案
COPY composer.json composer.lock ./

# 顯示 composer 檔案內容
RUN echo "=== composer.json 內容 ===" && cat composer.json
RUN echo "=== composer.lock 檔案大小 ===" && ls -la composer.lock

# 執行 composer install 並顯示詳細輸出
RUN echo "=== 開始執行 composer install ===" && \
    composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --verbose

# 檢查安裝結果
RUN echo "=== 檢查 vendor 目錄 ===" && ls -la vendor/
RUN echo "=== 檢查 autoload.php ===" && ls -la vendor/autoload.php
RUN echo "=== 檢查 Laravel 框架 ===" && ls -la vendor/laravel/

# 複製應用程式檔案
COPY . .

# 執行 composer dump-autoload
RUN echo "=== 執行 composer dump-autoload ===" && \
    composer dump-autoload --optimize --no-dev --verbose

# 測試 PHP 載入
RUN echo "=== 測試 PHP autoload ===" && \
    php -r "require 'vendor/autoload.php'; echo 'Autoload 成功載入\n';"

# 測試 Laravel
RUN echo "=== 測試 Laravel ===" && \
    php artisan --version || echo "Laravel 測試失敗"

CMD ["php-fpm"]
EOF

echo "1. 建立除錯用 Docker 映像檔..."
docker build -f Dockerfile.debug -t laravel-debug . --no-cache

if [ $? -eq 0 ]; then
    echo "✓ 除錯映像檔建置成功"
    
    echo ""
    echo "2. 執行容器並檢查 composer 狀態..."
    docker run --rm laravel-debug composer show --installed
    
    echo ""
    echo "3. 檢查 Laravel 相關套件..."
    docker run --rm laravel-debug composer show laravel/framework
    
else
    echo "✗ 除錯映像檔建置失敗"
fi

# 清理
echo ""
echo "4. 清理除錯檔案..."
rm -f Dockerfile.debug
docker rmi laravel-debug 2>/dev/null || true

echo ""
echo "=== 除錯完成 ==="