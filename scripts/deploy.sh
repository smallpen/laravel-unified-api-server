#!/bin/bash

# Laravel 統一 API Server 自動化部署腳本
# 版本: 1.0
# 作者: System Administrator

set -e  # 遇到錯誤立即退出

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日誌函數
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# 配置變數
PROJECT_NAME="laravel-unified-api-server"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
BACKUP_DIR="./backups"
LOG_DIR="./logs"

# 檢查必要工具
check_requirements() {
    log_info "檢查部署環境需求..."
    
    # 檢查 Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker 未安裝，請先安裝 Docker"
        exit 1
    fi
    
    # 檢查 Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose 未安裝，請先安裝 Docker Compose"
        exit 1
    fi
    
    # 檢查配置檔案
    if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then
        log_error "找不到 Docker Compose 配置檔案: $DOCKER_COMPOSE_FILE"
        exit 1
    fi
    
    if [ ! -f ".env" ]; then
        log_error "找不到環境變數檔案: .env"
        exit 1
    fi
    
    log_success "環境檢查完成"
}

# 建立必要目錄
create_directories() {
    log_info "建立必要目錄..."
    
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$LOG_DIR"
    mkdir -p "storage/logs"
    mkdir -p "storage/app/public"
    
    log_success "目錄建立完成"
}

# 備份現有資料
backup_data() {
    log_info "備份現有資料..."
    
    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_PATH="$BACKUP_DIR/backup_$BACKUP_TIMESTAMP"
    
    mkdir -p "$BACKUP_PATH"
    
    # 備份資料庫
    if docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "database"; then
        log_info "備份資料庫..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T database mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > "$BACKUP_PATH/database.sql"
        log_success "資料庫備份完成"
    fi
    
    # 備份上傳檔案
    if [ -d "storage/app/public" ]; then
        log_info "備份上傳檔案..."
        cp -r storage/app/public "$BACKUP_PATH/"
        log_success "檔案備份完成"
    fi
    
    log_success "資料備份完成: $BACKUP_PATH"
}

# 拉取最新程式碼
pull_latest_code() {
    log_info "拉取最新程式碼..."
    
    if [ -d ".git" ]; then
        git fetch origin
        git reset --hard origin/main
        log_success "程式碼更新完成"
    else
        log_warning "非 Git 專案，跳過程式碼拉取"
    fi
}

# 建置 Docker 映像
build_images() {
    log_info "建置 Docker 映像..."
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" build --no-cache
    
    log_success "Docker 映像建置完成"
}

# 停止現有服務
stop_services() {
    log_info "停止現有服務..."
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" down
    
    log_success "服務停止完成"
}

# 啟動服務
start_services() {
    log_info "啟動服務..."
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    log_success "服務啟動完成"
}

# 執行資料庫遷移
run_migrations() {
    log_info "執行資料庫遷移..."
    
    # 等待資料庫啟動
    sleep 10
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel php artisan migrate --force
    
    log_success "資料庫遷移完成"
}

# 清除快取
clear_cache() {
    log_info "清除應用程式快取..."
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel php artisan config:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel php artisan route:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel php artisan view:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel php artisan cache:clear
    
    log_success "快取清除完成"
}

# 設定檔案權限
set_permissions() {
    log_info "設定檔案權限..."
    
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel chown -R www-data:www-data /var/www/html/storage
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T laravel chmod -R 775 /var/www/html/storage
    
    log_success "檔案權限設定完成"
}

# 健康檢查
health_check() {
    log_info "執行健康檢查..."
    
    # 檢查服務狀態
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "Up"; then
        log_error "服務未正常啟動"
        return 1
    fi
    
    # 檢查 API 端點
    sleep 5
    if curl -f -s http://localhost/api/health > /dev/null; then
        log_success "API 健康檢查通過"
    else
        log_error "API 健康檢查失敗"
        return 1
    fi
    
    log_success "健康檢查完成"
}

# 清理舊的備份檔案（保留最近 7 天）
cleanup_old_backups() {
    log_info "清理舊的備份檔案..."
    
    find "$BACKUP_DIR" -type d -name "backup_*" -mtime +7 -exec rm -rf {} \; 2>/dev/null || true
    
    log_success "舊備份檔案清理完成"
}

# 主要部署流程
main() {
    log_info "開始部署 $PROJECT_NAME..."
    
    # 載入環境變數
    if [ -f ".env" ]; then
        source .env
    fi
    
    check_requirements
    create_directories
    backup_data
    pull_latest_code
    build_images
    stop_services
    start_services
    run_migrations
    clear_cache
    set_permissions
    
    if health_check; then
        cleanup_old_backups
        log_success "部署完成！"
        log_info "服務狀態："
        docker-compose -f "$DOCKER_COMPOSE_FILE" ps
    else
        log_error "部署失敗，請檢查日誌"
        exit 1
    fi
}

# 顯示使用說明
show_usage() {
    echo "使用方法: $0 [選項]"
    echo ""
    echo "選項:"
    echo "  deploy    執行完整部署流程"
    echo "  backup    僅執行資料備份"
    echo "  health    執行健康檢查"
    echo "  logs      顯示服務日誌"
    echo "  status    顯示服務狀態"
    echo "  help      顯示此說明"
    echo ""
}

# 顯示服務日誌
show_logs() {
    docker-compose -f "$DOCKER_COMPOSE_FILE" logs -f --tail=100
}

# 顯示服務狀態
show_status() {
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
}

# 處理命令列參數
case "${1:-deploy}" in
    "deploy")
        main
        ;;
    "backup")
        backup_data
        ;;
    "health")
        health_check
        ;;
    "logs")
        show_logs
        ;;
    "status")
        show_status
        ;;
    "help")
        show_usage
        ;;
    *)
        log_error "未知的選項: $1"
        show_usage
        exit 1
        ;;
esac