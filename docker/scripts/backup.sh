#!/bin/bash

# 統一API Server備份腳本

set -e

ENVIRONMENT=${1:-development}
BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="unified_api_backup_${TIMESTAMP}"

echo "=== 統一API Server備份 ==="
echo "環境: $ENVIRONMENT"
echo "備份名稱: $BACKUP_NAME"

# 建立備份目錄
mkdir -p "$BACKUP_DIR"

echo "建立備份目錄: $BACKUP_DIR/$BACKUP_NAME"
mkdir -p "$BACKUP_DIR/$BACKUP_NAME"

# 備份資料庫
echo "備份資料庫..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker compose -f docker compose.yml -f docker compose.prod.yml exec -T database mysqldump -u root -p"${DB_ROOT_PASSWORD:-root_password}" --all-databases > "$BACKUP_DIR/$BACKUP_NAME/database.sql"
else
    docker compose exec -T database mysqldump -u root -p"${DB_ROOT_PASSWORD:-root_password}" --all-databases > "$BACKUP_DIR/$BACKUP_NAME/database.sql"
fi

# 備份Redis資料
echo "備份Redis資料..."
if [ "$ENVIRONMENT" = "production" ]; then
    docker compose -f docker compose.yml -f docker compose.prod.yml exec -T redis redis-cli BGSAVE
    sleep 5
    docker cp unified-api-redis:/data/dump.rdb "$BACKUP_DIR/$BACKUP_NAME/redis_dump.rdb"
else
    docker compose exec -T redis redis-cli BGSAVE
    sleep 5
    docker cp unified-api-redis:/data/dump.rdb "$BACKUP_DIR/$BACKUP_NAME/redis_dump.rdb"
fi

# 備份應用程式檔案
echo "備份應用程式檔案..."
tar -czf "$BACKUP_DIR/$BACKUP_NAME/app_files.tar.gz" \
    --exclude='./node_modules' \
    --exclude='./vendor' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./.git' \
    --exclude='./backups' \
    .

# 備份環境設定
echo "備份環境設定..."
cp .env "$BACKUP_DIR/$BACKUP_NAME/env_backup" 2>/dev/null || echo "警告: .env檔案不存在"

# 建立備份資訊檔案
echo "建立備份資訊..."
cat > "$BACKUP_DIR/$BACKUP_NAME/backup_info.txt" << EOF
統一API Server備份資訊
=====================

備份時間: $(date)
環境: $ENVIRONMENT
備份名稱: $BACKUP_NAME

包含內容:
- 資料庫完整備份 (database.sql)
- Redis資料備份 (redis_dump.rdb)
- 應用程式檔案 (app_files.tar.gz)
- 環境設定檔案 (env_backup)

還原指令:
1. 解壓應用程式檔案: tar -xzf app_files.tar.gz
2. 還原環境設定: cp env_backup .env
3. 還原資料庫: docker compose exec -T database mysql -u root -p < database.sql
4. 還原Redis: docker cp redis_dump.rdb unified-api-redis:/data/dump.rdb && docker compose exec redis redis-cli DEBUG RESTART

備份大小: $(du -sh "$BACKUP_DIR/$BACKUP_NAME" | cut -f1)
EOF

# 壓縮整個備份
echo "壓縮備份檔案..."
cd "$BACKUP_DIR"
tar -czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME"
rm -rf "$BACKUP_NAME"
cd - > /dev/null

echo ""
echo "=== 備份完成 ==="
echo "備份檔案: $BACKUP_DIR/${BACKUP_NAME}.tar.gz"
echo "備份大小: $(du -sh "$BACKUP_DIR/${BACKUP_NAME}.tar.gz" | cut -f1)"

# 清理舊備份（保留最近10個）
echo ""
echo "清理舊備份檔案..."
cd "$BACKUP_DIR"
ls -t unified_api_backup_*.tar.gz | tail -n +11 | xargs -r rm -f
echo "保留最近10個備份檔案"
cd - > /dev/null

echo ""
echo "現有備份檔案："
ls -lah "$BACKUP_DIR"/unified_api_backup_*.tar.gz 2>/dev/null || echo "無備份檔案"