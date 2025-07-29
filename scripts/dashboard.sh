#!/bin/bash

# Laravel 統一 API Server 系統狀態儀表板
# 版本: 1.0
# 作者: System Administrator

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# 配置變數
PROJECT_NAME="Laravel 統一 API Server"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
HEALTH_CHECK_URL="http://localhost/api/health/detailed"

# 清除螢幕
clear_screen() {
    clear
}

# 顯示標題
show_header() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                    ${PROJECT_NAME} 系統儀表板                    ║${NC}"
    echo -e "${CYAN}║                      $(date '+%Y-%m-%d %H:%M:%S')                      ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# 檢查容器狀態
check_containers() {
    echo -e "${BLUE}📦 容器狀態${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    local containers=("nginx" "laravel" "database" "redis")
    local all_healthy=true
    
    for container in "${containers[@]}"; do
        if docker-compose -f "$DOCKER_COMPOSE_FILE" ps "$container" 2>/dev/null | grep -q "Up"; then
            echo -e "  ${GREEN}✓${NC} $container: 運行中"
        else
            echo -e "  ${RED}✗${NC} $container: 停止"
            all_healthy=false
        fi
    done
    
    if $all_healthy; then
        echo -e "  ${GREEN}整體狀態: 所有容器正常運行${NC}"
    else
        echo -e "  ${RED}整體狀態: 部分容器異常${NC}"
    fi
    echo ""
}

# 檢查系統資源
check_resources() {
    echo -e "${BLUE}💻 系統資源${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # CPU 使用率
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    if (( $(echo "$cpu_usage > 80" | bc -l 2>/dev/null || echo "0") )); then
        echo -e "  ${RED}⚠${NC}  CPU 使用率: ${cpu_usage}% (高)"
    elif (( $(echo "$cpu_usage > 60" | bc -l 2>/dev/null || echo "0") )); then
        echo -e "  ${YELLOW}⚠${NC}  CPU 使用率: ${cpu_usage}% (中等)"
    else
        echo -e "  ${GREEN}✓${NC} CPU 使用率: ${cpu_usage}% (正常)"
    fi
    
    # 記憶體使用率
    local mem_info=$(free | grep Mem)
    local mem_total=$(echo $mem_info | awk '{print $2}')
    local mem_used=$(echo $mem_info | awk '{print $3}')
    local mem_usage=$(echo "scale=1; $mem_used * 100 / $mem_total" | bc -l 2>/dev/null || echo "0")
    
    if (( $(echo "$mem_usage > 80" | bc -l 2>/dev/null || echo "0") )); then
        echo -e "  ${RED}⚠${NC}  記憶體使用率: ${mem_usage}% (高)"
    elif (( $(echo "$mem_usage > 60" | bc -l 2>/dev/null || echo "0") )); then
        echo -e "  ${YELLOW}⚠${NC}  記憶體使用率: ${mem_usage}% (中等)"
    else
        echo -e "  ${GREEN}✓${NC} 記憶體使用率: ${mem_usage}% (正常)"
    fi
    
    # 磁碟使用率
    local disk_usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$disk_usage" -gt 80 ]; then
        echo -e "  ${RED}⚠${NC}  磁碟使用率: ${disk_usage}% (高)"
    elif [ "$disk_usage" -gt 60 ]; then
        echo -e "  ${YELLOW}⚠${NC}  磁碟使用率: ${disk_usage}% (中等)"
    else
        echo -e "  ${GREEN}✓${NC} 磁碟使用率: ${disk_usage}% (正常)"
    fi
    
    echo ""
}

# 檢查 API 健康狀態
check_api_health() {
    echo -e "${BLUE}🔍 API 健康檢查${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    local response
    local http_code
    
    response=$(curl -s -w "HTTPSTATUS:%{http_code}" "$HEALTH_CHECK_URL" 2>/dev/null || echo "HTTPSTATUS:000")
    http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
    
    if [ "$http_code" -eq 200 ]; then
        echo -e "  ${GREEN}✓${NC} API 端點: 正常 (HTTP $http_code)"
        
        # 解析健康檢查回應
        local health_data=$(echo "$response" | sed -e 's/HTTPSTATUS:.*//g')
        
        if command -v jq &> /dev/null && echo "$health_data" | jq . &> /dev/null; then
            local overall_status=$(echo "$health_data" | jq -r '.status // "unknown"')
            
            if [ "$overall_status" = "healthy" ]; then
                echo -e "  ${GREEN}✓${NC} 整體狀態: 健康"
            else
                echo -e "  ${RED}✗${NC} 整體狀態: $overall_status"
            fi
            
            # 檢查各個組件
            local components=("database" "redis" "storage" "cache")
            for component in "${components[@]}"; do
                local comp_status=$(echo "$health_data" | jq -r ".checks.$component.status // \"unknown\"")
                if [ "$comp_status" = "healthy" ]; then
                    echo -e "    ${GREEN}✓${NC} $component: 正常"
                elif [ "$comp_status" = "unhealthy" ]; then
                    echo -e "    ${RED}✗${NC} $component: 異常"
                else
                    echo -e "    ${YELLOW}?${NC} $component: 未知"
                fi
            done
        fi
    else
        echo -e "  ${RED}✗${NC} API 端點: 異常 (HTTP $http_code)"
    fi
    
    echo ""
}

# 檢查最近的日誌
check_recent_logs() {
    echo -e "${BLUE}📋 最近日誌${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 檢查錯誤日誌
    local error_count=0
    if [ -f "storage/logs/laravel.log" ]; then
        error_count=$(grep -c "ERROR\|CRITICAL\|EMERGENCY" storage/logs/laravel.log 2>/dev/null | tail -100 | wc -l || echo "0")
    fi
    
    if [ "$error_count" -gt 10 ]; then
        echo -e "  ${RED}⚠${NC}  最近錯誤: $error_count 條 (多)"
    elif [ "$error_count" -gt 0 ]; then
        echo -e "  ${YELLOW}⚠${NC}  最近錯誤: $error_count 條"
    else
        echo -e "  ${GREEN}✓${NC} 最近錯誤: 無"
    fi
    
    # 檢查 API 請求日誌
    local request_count=0
    if ls storage/logs/api_requests-*.log 1> /dev/null 2>&1; then
        request_count=$(find storage/logs -name "api_requests-*.log" -mtime -1 -exec cat {} \; 2>/dev/null | wc -l || echo "0")
    fi
    
    echo -e "  ${BLUE}ℹ${NC}  今日 API 請求: $request_count 次"
    
    # 顯示最近的錯誤 (如果有)
    if [ "$error_count" -gt 0 ] && [ -f "storage/logs/laravel.log" ]; then
        echo -e "  ${YELLOW}最近錯誤:${NC}"
        grep "ERROR\|CRITICAL\|EMERGENCY" storage/logs/laravel.log 2>/dev/null | tail -3 | while read line; do
            echo "    $(echo "$line" | cut -c1-80)..."
        done
    fi
    
    echo ""
}

# 檢查網路連線
check_network() {
    echo -e "${BLUE}🌐 網路連線${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 檢查監聽埠
    local ports=("80:HTTP" "443:HTTPS" "3306:MySQL" "6379:Redis")
    
    for port_info in "${ports[@]}"; do
        local port=$(echo "$port_info" | cut -d: -f1)
        local service=$(echo "$port_info" | cut -d: -f2)
        
        if netstat -tuln 2>/dev/null | grep -q ":$port "; then
            echo -e "  ${GREEN}✓${NC} $service (埠 $port): 監聽中"
        else
            echo -e "  ${RED}✗${NC} $service (埠 $port): 未監聽"
        fi
    done
    
    echo ""
}

# 顯示系統統計
show_statistics() {
    echo -e "${BLUE}📊 系統統計${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 系統運行時間
    local uptime_info=$(uptime | awk -F'up ' '{print $2}' | awk -F',' '{print $1}')
    echo -e "  ${CYAN}⏱${NC}  系統運行時間: $uptime_info"
    
    # Docker 容器運行時間
    if docker-compose -f "$DOCKER_COMPOSE_FILE" ps laravel 2>/dev/null | grep -q "Up"; then
        local container_uptime=$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps laravel | grep "Up" | awk '{for(i=4;i<=NF;i++) printf "%s ", $i; print ""}')
        echo -e "  ${CYAN}📦${NC} 容器運行時間: $container_uptime"
    fi
    
    # 日誌檔案大小
    local log_size=$(du -sh storage/logs 2>/dev/null | awk '{print $1}' || echo "未知")
    echo -e "  ${CYAN}📄${NC} 日誌檔案大小: $log_size"
    
    # 資料庫大小 (如果可以連接)
    if docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T database mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" &>/dev/null; then
        local db_size=$(docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T database mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='$MYSQL_DATABASE';" 2>/dev/null | tail -1 || echo "未知")
        echo -e "  ${CYAN}🗄${NC}  資料庫大小: ${db_size} MB"
    fi
    
    echo ""
}

# 顯示快速操作選單
show_quick_actions() {
    echo -e "${BLUE}⚡ 快速操作${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "  ${GREEN}1${NC}) 重新整理儀表板"
    echo -e "  ${GREEN}2${NC}) 檢視容器日誌"
    echo -e "  ${GREEN}3${NC}) 重啟服務"
    echo -e "  ${GREEN}4${NC}) 執行健康檢查"
    echo -e "  ${GREEN}5${NC}) 生成系統報告"
    echo -e "  ${GREEN}6${NC}) 檢視詳細監控"
    echo -e "  ${GREEN}q${NC}) 退出"
    echo ""
}

# 處理使用者輸入
handle_user_input() {
    echo -n "請選擇操作 [1-6/q]: "
    read -r choice
    
    case $choice in
        1)
            return 0  # 重新整理
            ;;
        2)
            echo "檢視容器日誌..."
            docker-compose -f "$DOCKER_COMPOSE_FILE" logs --tail=50
            echo ""
            echo "按任意鍵繼續..."
            read -r
            ;;
        3)
            echo "重啟服務..."
            docker-compose -f "$DOCKER_COMPOSE_FILE" restart
            echo "服務重啟完成"
            echo "按任意鍵繼續..."
            read -r
            ;;
        4)
            echo "執行健康檢查..."
            ./scripts/monitor.sh health
            echo ""
            echo "按任意鍵繼續..."
            read -r
            ;;
        5)
            echo "生成系統報告..."
            ./scripts/monitor.sh report
            echo "報告已生成"
            echo "按任意鍵繼續..."
            read -r
            ;;
        6)
            echo "執行詳細監控..."
            ./scripts/monitor.sh monitor
            echo ""
            echo "按任意鍵繼續..."
            read -r
            ;;
        q|Q)
            echo "退出儀表板..."
            exit 0
            ;;
        *)
            echo "無效選擇，請重新選擇"
            sleep 1
            ;;
    esac
}

# 主要儀表板循環
main_dashboard() {
    while true; do
        clear_screen
        show_header
        check_containers
        check_resources
        check_api_health
        check_recent_logs
        check_network
        show_statistics
        show_quick_actions
        
        handle_user_input
    done
}

# 一次性顯示模式
show_once() {
    clear_screen
    show_header
    check_containers
    check_resources
    check_api_health
    check_recent_logs
    check_network
    show_statistics
}

# 顯示使用說明
show_usage() {
    echo "使用方法: $0 [選項]"
    echo ""
    echo "選項:"
    echo "  dashboard  啟動互動式儀表板 (預設)"
    echo "  once       顯示一次狀態後退出"
    echo "  help       顯示此說明"
    echo ""
}

# 處理命令列參數
case "${1:-dashboard}" in
    "dashboard")
        main_dashboard
        ;;
    "once")
        show_once
        ;;
    "help")
        show_usage
        ;;
    *)
        echo "未知的選項: $1"
        show_usage
        exit 1
        ;;
esac