# 多階段建構 - 建構階段
FROM php:8.1-fpm AS builder

# 設定工作目錄
WORKDIR /var/www/html

# 安裝系統依賴和PHP擴充
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
    libmcrypt-dev \
    libicu-dev \
    supervisor \
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

# 安裝Redis擴充
RUN pecl install redis && docker-php-ext-enable redis

# 清理APT快取
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 複製composer檔案並安裝依賴
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 生產階段
FROM php:8.1-fpm

# 設定工作目錄
WORKDIR /var/www/html

# 從建構階段複製已安裝的擴充和依賴
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /var/www/html/vendor/ /var/www/html/vendor/
COPY --from=builder /usr/bin/composer /usr/bin/composer

# 安裝運行時依賴
RUN apt-get update && apt-get install -y \
    libpng16-16 \
    libonig5 \
    libxml2 \
    libzip4 \
    libpq5 \
    libfreetype6 \
    libjpeg62-turbo \
    libicu72 \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 建立Laravel使用者
RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www

# 複製PHP配置檔案
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# 複製supervisor配置和啟動腳本
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/scripts/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# 複製應用程式檔案
COPY --chown=www:www . /var/www/html

# 確保 composer 依賴完整性
RUN composer dump-autoload --optimize --no-dev

# 建立必要的Laravel目錄結構並設定權限
RUN mkdir -p /var/www/html/storage/app/public && \
    mkdir -p /var/www/html/storage/framework/cache/data && \
    mkdir -p /var/www/html/storage/framework/sessions && \
    mkdir -p /var/www/html/storage/framework/views && \
    mkdir -p /var/www/html/storage/logs && \
    mkdir -p /var/www/html/bootstrap/cache && \
    chown -R www:www /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# 建立supervisor日誌目錄
RUN mkdir -p /var/log/supervisor && \
    chown -R www:www /var/log/supervisor && \
    chmod -R 755 /var/log/supervisor

# 切換到www使用者
USER www

# 執行Laravel優化命令（移除快取命令，讓啟動腳本處理）
RUN php artisan package:discover --ansi

# 切換回root用戶以啟動supervisor
USER root

# 暴露連接埠9000
EXPOSE 9000

# 使用啟動腳本
CMD ["/usr/local/bin/start.sh"]