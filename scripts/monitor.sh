#!/bin/bash

# Laravel 統一 API Server 監控腳本
# 版本: 1.0
# 作者: System Administrator

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 配置變數
PROJECT_NAME="laravel-unified-api-server"
DOCKER_COMPOSE_FILE="docker compose.prod.yml"
HEALTH_CHECK_URL="http://localhost/api/health/detailed"
LOG_DIR="./logs"
ALERT_EMAIL=""
SLACK_WEBHOOK=""

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

# 建立日誌目錄
mkdir -p "$LOG_DIR"

# 檢查 Docker 容器狀態
check_containers() {
    log_info "檢查 Docker 容器狀態..."
    
    local containers=("nginx" "laravel" "database" "redis")
    local failed_containers=()
    
    for container in "${containers[@]}"; do
        if ! docker compose -f "$DOCKER_COMPOSE_FILE" ps "$container" | grep -q "Up"; then
            failed_containers+=("$container")
            log_error "容器 $container 未正常運行"
        else
            log_success "容器 $container 運行正常"
        fi
    done
    
    if [ ${#failed_containers[@]} -gt 0 ]; then
        send_alert "容器狀態異常" "以下容器未正常運行: ${failed_containers[*]}"
        return 1
    fi
    
    return 0
}

# 檢查系統健康狀態
check_health() {
    log_info "檢查系統健康狀態..."
    
    local response
    local http_code
    
    response=$(curl -s -w "HTTPSTATUS:%{http_code}" "$HEALTH_CHECK_URL" || echo "HTTPSTATUS:000")
    http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
    
    if [ "$http_code" -eq 200 ]; then
        log_success "健康檢查通過"
        return 0
    else
        log_error "健康檢查失敗，HTTP 狀態碼: $http_code"
        send_alert "健康檢查失敗" "API 健康檢查返回狀態碼: $http_code"
        return 1
    fi
}

# 檢查系統資源使用率
check_resources() {
    log_info "檢查系統資源使用率..."
    
    # 檢查 CPU 使用率
    local cpu_usage
    cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    
    if (( $(echo "$cpu_usage > 80" | bc -l) )); then
        log_warning "CPU 使用率過高: ${cpu_usage}%"
        send_alert "CPU 使用率警告" "當前 CPU 使用率: ${cpu_usage}%"
    else
        log_success "CPU 使用率正常: ${cpu_usage}%"
    fi
    
    # 檢查記憶體使用率
    local mem_usage
    mem_usage=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')
    
    if (( $(echo "$mem_usage > 80" | bc -l) )); then
        log_warning "記憶體使用率過高: ${mem_usage}%"
        send_alert "記憶體使用率警告" "當前記憶體使用率: ${mem_usage}%"
    else
        log_success "記憶體使用率正常: ${mem_usage}%"
    fi
    
    # 檢查磁碟使用率
    local disk_usage
    disk_usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$disk_usage" -gt 80 ]; then
        log_warning "磁碟使用率過高: ${disk_usage}%"
        send_alert "磁碟使用率警告" "當前磁碟使用率: ${disk_usage}%"
    else
        log_success "磁碟使用率正常: ${disk_usage}%"
    fi
}

# 檢查應用程式日誌錯誤
check_logs() {
    log_info "檢查應用程式日誌錯誤..."
    
    local error_count
    local log_file="storage/logs/laravel.log"
    
    if [ -f "$log_file" ]; then
        # 檢查最近 5 分鐘的錯誤日誌
        error_count=$(grep -c "ERROR\|CRITICAL\|EMERGENCY" "$log_file" | tail -100 | wc -l)
        
        if [ "$error_count" -gt 10 ]; then
            log_warning "發現大量錯誤日誌: $error_count 條"
            send_alert "應用程式錯誤警告" "最近發現 $error_count 條錯誤日誌"
        else
            log_success "應用程式日誌正常"
        fi
    else
        log_warning "找不到應用程式日誌檔案"
    fi
}

# 檢查資料庫連線
check_database() {
    log_info "檢查資料庫連線..."
    
    if docker compose -f "$DOCKER_COMPOSE_FILE" exec -T database mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
        log_success "資料庫連線正常"
        return 0
    else
        log_error "資料庫連線失敗"
        send_alert "資料庫連線失敗" "無法連接到 MySQL 資料庫"
        return 1
    fi
}

# 檢查 Redis 連線
check_redis() {
    log_info "檢查 Redis 連線..."
    
    if docker compose -f "$DOCKER_COMPOSE_FILE" exec -T redis redis-cli ping | grep -q "PONG"; then
        log_success "Redis 連線正常"
        return 0
    else
        log_error "Redis 連線失敗"
        send_alert "Redis 連線失敗" "無法連接到 Redis 服務"
        return 1
    fi
}

# 發送警報通知
send_alert() {
    local title="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    log_warning "發送警報: $title - $message"
    
    # 記錄到警報日誌
    echo "[$timestamp] $title: $message" >> "$LOG_DIR/alerts.log"
    
    # 發送郵件通知（如果配置了郵件地址）
    if [ -n "$ALERT_EMAIL" ]; then
        echo "時間: $timestamp
標題: $title
內容: $message
服務: $PROJECT_NAME" | mail -s "[$PROJECT_NAME] $title" "$ALERT_EMAIL"
    fi
    
    # 發送 Slack 通知（如果配置了 Webhook）
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"[$PROJECT_NAME] $title: $message\"}" \
            "$SLACK_WEBHOOK"
    fi
}

# 生成監控報告
generate_report() {
    local report_file="$LOG_DIR/monitor_report_$(date +%Y%m%d_%H%M%S).txt"
    
    log_info "生成監控報告: $report_file"
    
    {
        echo "Laravel 統一 API Server 監控報告"
        echo "生成時間: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "========================================"
        echo ""
        
        echo "容器狀態:"
        docker compose -f "$DOCKER_COMPOSE_FILE" ps
        echo ""
        
        echo "系統資源使用率:"
        echo "CPU: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}')"
        echo "記憶體: $(free -h | grep Mem)"
        echo "磁碟: $(df -h /)"
        echo ""
        
        echo "網路連線:"
        netstat -tuln | grep LISTEN
        echo ""
        
        echo "最近的錯誤日誌:"
        if [ -f "storage/logs/laravel.log" ]; then
            tail -20 storage/logs/laravel.log | grep -E "ERROR|CRITICAL|EMERGENCY" || echo "無錯誤日誌"
        else
            echo "找不到日誌檔案"
        fi
        
    } > "$report_file"
    
    log_success "監控報告已生成: $report_file"
}

# 自動修復功能
auto_repair() {
    log_info "執行自動修復..."
    
    # 重啟失敗的容器
    local containers=("nginx" "laravel" "database" "redis")
    
    for container in "${containers[@]}"; do
        if ! docker compose -f "$DOCKER_COMPOSE_FILE" ps "$container" | grep -q "Up"; then
            log_warning "嘗試重啟容器: $container"
            docker compose -f "$DOCKER_COMPOSE_FILE" restart "$container"
            sleep 10
            
            if docker compose -f "$DOCKER_COMPOSE_FILE" ps "$container" | grep -q "Up"; then
                log_success "容器 $container 重啟成功"
            else
                log_error "容器 $container 重啟失敗"
            fi
        fi
    done
    
    # 清理日誌檔案（如果過大）
    local log_file="storage/logs/laravel.log"
    if [ -f "$log_file" ] && [ $(stat -c%s "$log_file") -gt 104857600 ]; then # 100MB
        log_info "清理過大的日誌檔案"
        tail -1000 "$log_file" > "${log_file}.tmp"
        mv "${log_file}.tmp" "$log_file"
    fi
}

# 主要監控流程
main() {
    log_info "開始監控 $PROJECT_NAME..."
    
    local failed_checks=0
    
    # 載入環境變數
    if [ -f ".env" ]; then
        source .env
    fi
    
    # 執行各項檢查
    check_containers || ((failed_checks++))
    check_health || ((failed_checks++))
    check_resources
    check_logs
    check_database || ((failed_checks++))
    check_redis || ((failed_checks++))
    
    # 如果有檢查失敗，嘗試自動修復
    if [ $failed_checks -gt 0 ]; then
        log_warning "發現 $failed_checks 項檢查失敗，嘗試自動修復..."
        auto_repair
        
        # 重新檢查
        sleep 30
        check_containers && check_health && check_database && check_redis
    fi
    
    log_success "監控檢查完成"
}

# 顯示使用說明
show_usage() {
    echo "使用方法: $0 [選項]"
    echo ""
    echo "選項:"
    echo "  monitor   執行完整監控檢查"
    echo "  health    僅檢查健康狀態"
    echo "  resources 僅檢查系統資源"
    echo "  report    生成監控報告"
    echo "  repair    執行自動修復"
    echo "  help      顯示此說明"
    echo ""
}

# 處理命令列參數
case "${1:-monitor}" in
    "monitor")
        main
        ;;
    "health")
        check_health
        ;;
    "resources")
        check_resources
        ;;
    "report")
        generate_report
        ;;
    "repair")
        auto_repair
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