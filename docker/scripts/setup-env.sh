#!/bin/bash

# 環境變數設定腳本

ENV_FILE=".env"
ENV_EXAMPLE=".env.example"

echo "正在設定環境變數..."

# 檢查.env檔案是否存在
if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$ENV_EXAMPLE" ]; then
        echo "複製 $ENV_EXAMPLE 到 $ENV_FILE"
        cp "$ENV_EXAMPLE" "$ENV_FILE"
    else
        echo "錯誤: $ENV_EXAMPLE 檔案不存在"
        exit 1
    fi
fi

# 生成APP_KEY（如果尚未設定）
if ! grep -q "APP_KEY=base64:" "$ENV_FILE"; then
    echo "生成Laravel應用程式金鑰..."
    
    # 檢查是否在Docker容器中
    if [ -f /.dockerenv ]; then
        php artisan key:generate
    else
        # 在主機上執行
        if command -v docker-compose &> /dev/null; then
            docker-compose exec laravel php artisan key:generate
        else
            echo "警告: 無法生成APP_KEY，請手動執行 'php artisan key:generate'"
        fi
    fi
fi

# 設定預設環境變數（如果未設定）
set_env_var() {
    local var_name="$1"
    local default_value="$2"
    
    if ! grep -q "^$var_name=" "$ENV_FILE"; then
        echo "設定 $var_name=$default_value"
        echo "$var_name=$default_value" >> "$ENV_FILE"
    fi
}

# 設定Docker相關環境變數
set_env_var "NGINX_HTTP_PORT" "80"
set_env_var "NGINX_HTTPS_PORT" "443"
set_env_var "DB_EXTERNAL_PORT" "3306"
set_env_var "REDIS_EXTERNAL_PORT" "6379"
set_env_var "PHPMYADMIN_PORT" "8080"
set_env_var "MAILPIT_WEB_PORT" "8025"
set_env_var "MAILPIT_SMTP_PORT" "1025"
set_env_var "TIMEZONE" "Asia/Taipei"

# 設定資料庫密碼（如果未設定）
set_env_var "DB_ROOT_PASSWORD" "$(openssl rand -base64 32)"

echo "環境變數設定完成！"
echo ""
echo "請檢查 $ENV_FILE 檔案並根據需要調整設定。"
echo ""
echo "重要設定項目："
echo "- APP_ENV: 設定為 'production' 用於生產環境"
echo "- APP_DEBUG: 生產環境應設定為 'false'"
echo "- DB_PASSWORD: 請設定強密碼"
echo "- DB_ROOT_PASSWORD: 請設定強密碼"