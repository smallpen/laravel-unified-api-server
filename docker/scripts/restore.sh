#!/bin/bash

# 統一API Server還原腳本

set -e

BACKUP_FILE="$1"
ENVIRONMENT=${2:-development}

if [ -z "$BACKUP_FILE" ]; then
    echo "使用方法: $0 <備份檔案> [環境]"
    echo ""
    echo "可用的備份檔案："
    ls -lah ./backups/unified_api_backup_*.tar.gz 2>/dev/null || echo "無備份檔案"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "錯誤: 備份檔案 '$BACKUP_FILE' 不存在"
    exit 1
fi

echo "=== 統一API Server還原 ==="
echo "備份檔案: $BACKUP_FILE"
echo "環境: $ENVIRONMENT"
echo ""

# 確認還原操作
read -p "這將覆蓋現有資料，確定要繼續嗎？ (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "還原操作已取消"
    exit 1
fi

# 停止服務
echo "停止現有服務..."
./docker/scripts/stop.sh "$ENVIRONMENT"

# 解壓備份檔案
TEMP_DIR=$(mktemp -d)
echo "解壓備份檔案到臨時目錄: $TEMP_DIR"
tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"

BACKUP_NAME=$(basename "$BACKUP_FILE" .tar.gz)
BACKUP_PATH="$TEMP_DIR/$BACKUP_NAME"

# 檢查備份內容
if [ ! -d "$BACKUP_PATH" ]; then
    echo "錯誤: 備份檔案格式不正確"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "備份資訊："
cat "$BACKUP_PATH/backup_info.txt" 2>/dev/null || echo "無備份資訊檔案"
echo ""

# 還原應用程式檔案
echo "還原應用程式檔案..."
if [ -f "$BACKUP_PATH/app_files.tar.gz" ]; then
    tar -xzf "$BACKUP_PATH/app_files.tar.gz" -C .
    echo "✓ 應用程式檔案還原完成"
else
    echo "⚠ 未找到應用程式檔案備份"
fi

# 還原環境設定
echo "還原環境設定..."
if [ -f "$BACKUP_PATH/env_backup" ]; then
    cp "$BACKUP_PATH/env_backup" .env
    echo "✓ 環境設定還原完成"
else
    echo "⚠ 未找到環境設定備份"
fi

# 啟動服務
echo "啟動服務..."
./docker/scripts/start.sh "$ENVIRONMENT"

# 等待服務啟動
echo "等待服務啟動..."
sleep 30

# 還原資料庫
echo "還原資料庫..."
if [ -f "$BACKUP_PATH/database.sql" ]; then
    if [ "$ENVIRONMENT" = "production" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml exec -T database mysql -u root -p"${DB_ROOT_PASSWORD:-root_password}" < "$BACKUP_PATH/database.sql"
    else
        docker compose exec -T database mysql -u root -p"${DB_ROOT_PASSWORD:-root_password}" < "$BACKUP_PATH/database.sql"
    fi
    echo "✓ 資料庫還原完成"
else
    echo "⚠ 未找到資料庫備份"
fi

# 還原Redis資料
echo "還原Redis資料..."
if [ -f "$BACKUP_PATH/redis_dump.rdb" ]; then
    # 停止Redis以安全地替換資料檔案
    if [ "$ENVIRONMENT" = "production" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml stop redis
    else
        docker compose stop redis
    fi
    
    # 複製Redis備份檔案
    docker cp "$BACKUP_PATH/redis_dump.rdb" unified-api-redis:/data/dump.rdb
    
    # 重新啟動Redis
    if [ "$ENVIRONMENT" = "production" ]; then
        docker compose -f docker compose.yml -f docker compose.prod.yml start redis
    else
        docker compose start redis
    fi
    
    echo "✓ Redis資料還原完成"
else
    echo "⚠ 未找到Redis備份"
fi

# 清理臨時檔案
echo "清理臨時檔案..."
rm -rf "$TEMP_DIR"

# 重新建立快取
echo "重新建立快取..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker compose -f docker compose.yml -f docker compose.prod.yml exec laravel php artisan config:cache
    docker compose -f docker compose.yml -f docker compose.prod.yml exec laravel php artisan route:cache
    docker compose -f docker compose.yml -f docker compose.prod.yml exec laravel php artisan view:cache
else
    docker compose exec laravel php artisan config:cache
    docker compose exec laravel php artisan route:cache
    docker compose exec laravel php artisan view:cache
fi

echo ""
echo "=== 還原完成 ==="
echo ""
echo "請執行健康檢查以確認系統狀態："
echo "./docker/scripts/health-check.sh $ENVIRONMENT"