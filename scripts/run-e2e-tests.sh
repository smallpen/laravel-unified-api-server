#!/bin/bash

# 端到端系統測試執行腳本
# 在Docker環境中執行完整的系統測試

set -e

echo "=========================================="
echo "開始執行端到端系統測試"
echo "=========================================="

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日誌函數
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 檢查Docker環境
check_docker_environment() {
    log_info "檢查Docker環境..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker未安裝或不在PATH中"
        exit 1
    fi
    
    if ! command -v docker compose &> /dev/null; then
        log_error "Docker Compose未安裝或不在PATH中"
        exit 1
    fi
    
    log_success "Docker環境檢查通過"
}

# 啟動Docker服務
start_docker_services() {
    log_info "啟動Docker服務..."
    
    # 停止現有服務
    docker compose down --remove-orphans
    
    # 建立並啟動服務
    docker compose up -d --build
    
    # 等待服務啟動
    log_info "等待服務啟動..."
    sleep 30
    
    # 檢查服務狀態
    if ! docker compose ps | grep -q "Up"; then
        log_error "Docker服務啟動失敗"
        docker compose logs
        exit 1
    fi
    
    log_success "Docker服務啟動成功"
}

# 檢查服務健康狀態
check_service_health() {
    log_info "檢查服務健康狀態..."
    
    # 檢查Laravel應用程式
    max_attempts=30
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if docker compose exec -T laravel php artisan tinker --execute="echo 'Laravel OK';" &> /dev/null; then
            log_success "Laravel應用程式健康檢查通過"
            break
        fi
        
        log_warning "Laravel應用程式尚未就緒，等待中... (嘗試 $attempt/$max_attempts)"
        sleep 5
        ((attempt++))
    done
    
    if [ $attempt -gt $max_attempts ]; then
        log_error "Laravel應用程式健康檢查失敗"
        docker compose logs laravel
        exit 1
    fi
    
    # 檢查資料庫連線
    if docker compose exec -T laravel php artisan migrate:status &> /dev/null; then
        log_success "資料庫連線健康檢查通過"
    else
        log_error "資料庫連線健康檢查失敗"
        docker compose logs database
        exit 1
    fi
    
    # 檢查Redis連線
    if docker compose exec -T redis redis-cli ping | grep -q "PONG"; then
        log_success "Redis連線健康檢查通過"
    else
        log_error "Redis連線健康檢查失敗"
        docker compose logs redis
        exit 1
    fi
    
    # 檢查Nginx
    if docker compose exec -T nginx nginx -t &> /dev/null; then
        log_success "Nginx配置健康檢查通過"
    else
        log_error "Nginx配置健康檢查失敗"
        docker compose logs nginx
        exit 1
    fi
}

# 準備測試環境
prepare_test_environment() {
    log_info "準備測試環境..."
    
    # 執行資料庫遷移
    docker compose exec -T laravel php artisan migrate:fresh --force
    
    # 清除快取
    docker compose exec -T laravel php artisan cache:clear
    docker compose exec -T laravel php artisan config:clear
    docker compose exec -T laravel php artisan route:clear
    docker compose exec -T laravel php artisan view:clear
    
    # 重新載入配置
    docker compose exec -T laravel php artisan config:cache
    docker compose exec -T laravel php artisan route:cache
    
    log_success "測試環境準備完成"
}

# 執行端到端測試
run_e2e_tests() {
    log_info "執行端到端系統測試..."
    
    # 建立測試結果目錄
    mkdir -p storage/logs/tests
    
    # 執行特定的端到端測試
    test_results=0
    
    # 執行端到端系統測試
    log_info "執行EndToEndSystemTest..."
    if docker compose exec -T laravel php artisan test --testsuite=Feature --filter=EndToEndSystemTest --stop-on-failure; then
        log_success "EndToEndSystemTest 執行成功"
    else
        log_error "EndToEndSystemTest 執行失敗"
        test_results=1
    fi
    
    # 執行完整API整合測試
    log_info "執行CompleteApiIntegrationTest..."
    if docker compose exec -T laravel php artisan test --testsuite=Feature --filter=CompleteApiIntegrationTest --stop-on-failure; then
        log_success "CompleteApiIntegrationTest 執行成功"
    else
        log_error "CompleteApiIntegrationTest 執行失敗"
        test_results=1
    fi
    
    # 執行綜合整合測試
    log_info "執行ComprehensiveIntegrationTest..."
    if docker compose exec -T laravel php artisan test --testsuite=Feature --filter=ComprehensiveIntegrationTest --stop-on-failure; then
        log_success "ComprehensiveIntegrationTest 執行成功"
    else
        log_error "ComprehensiveIntegrationTest 執行失敗"
        test_results=1
    fi
    
    # 執行安全測試
    log_info "執行安全測試..."
    if docker compose exec -T laravel php artisan test --testsuite=Security --stop-on-failure; then
        log_success "安全測試執行成功"
    else
        log_error "安全測試執行失敗"
        test_results=1
    fi
    
    # 執行效能測試
    log_info "執行效能測試..."
    if docker compose exec -T laravel php artisan test --testsuite=Performance --stop-on-failure; then
        log_success "效能測試執行成功"
    else
        log_error "效能測試執行失敗"
        test_results=1
    fi
    
    return $test_results
}

# 測試API端點
test_api_endpoints() {
    log_info "測試API端點可用性..."
    
    # 等待Nginx啟動
    sleep 10
    
    # 測試健康檢查端點
    if curl -f -s http://localhost/health > /dev/null; then
        log_success "健康檢查端點可用"
    else
        log_error "健康檢查端點不可用"
        return 1
    fi
    
    # 測試API文件端點
    if curl -f -s http://localhost/api/docs > /dev/null; then
        log_success "API文件端點可用"
    else
        log_error "API文件端點不可用"
        return 1
    fi
    
    # 測試Swagger UI端點
    if curl -f -s http://localhost/api/docs/swagger > /dev/null; then
        log_success "Swagger UI端點可用"
    else
        log_error "Swagger UI端點不可用"
        return 1
    fi
    
    # 測試OpenAPI JSON端點
    if curl -f -s http://localhost/api/docs/openapi.json > /dev/null; then
        log_success "OpenAPI JSON端點可用"
    else
        log_error "OpenAPI JSON端點不可用"
        return 1
    fi
    
    return 0
}

# 生成測試報告
generate_test_report() {
    log_info "生成測試報告..."
    
    # 建立報告目錄
    mkdir -p storage/logs/reports
    
    # 生成測試報告
    report_file="storage/logs/reports/e2e-test-report-$(date +%Y%m%d-%H%M%S).md"
    
    cat > "$report_file" << EOF
# 端到端系統測試報告

## 測試執行資訊
- 執行時間: $(date)
- 測試環境: Docker
- 測試類型: 端到端系統測試

## 測試結果摘要
- Docker環境: ✅ 通過
- 服務健康檢查: ✅ 通過
- API端點測試: ✅ 通過
- 端到端系統測試: $([ $1 -eq 0 ] && echo "✅ 通過" || echo "❌ 失敗")

## 服務狀態
\`\`\`
$(docker compose ps)
\`\`\`

## 系統資源使用
\`\`\`
$(docker stats --no-stream)
\`\`\`

## 日誌摘要
### Laravel應用程式日誌
\`\`\`
$(docker compose logs --tail=50 laravel)
\`\`\`

### Nginx日誌
\`\`\`
$(docker compose logs --tail=20 nginx)
\`\`\`

### 資料庫日誌
\`\`\`
$(docker compose logs --tail=20 database)
\`\`\`

### Redis日誌
\`\`\`
$(docker compose logs --tail=20 redis)
\`\`\`
EOF
    
    log_success "測試報告已生成: $report_file"
}

# 清理環境
cleanup_environment() {
    log_info "清理測試環境..."
    
    if [ "$1" = "keep-running" ]; then
        log_info "保持Docker服務運行"
    else
        docker compose down --remove-orphans
        log_success "Docker服務已停止"
    fi
}

# 主執行流程
main() {
    local keep_running=false
    
    # 解析命令列參數
    while [[ $# -gt 0 ]]; do
        case $1 in
            --keep-running)
                keep_running=true
                shift
                ;;
            --help)
                echo "用法: $0 [選項]"
                echo "選項:"
                echo "  --keep-running    測試完成後保持Docker服務運行"
                echo "  --help           顯示此幫助訊息"
                exit 0
                ;;
            *)
                log_error "未知選項: $1"
                exit 1
                ;;
        esac
    done
    
    # 執行測試流程
    check_docker_environment
    start_docker_services
    check_service_health
    prepare_test_environment
    
    # 測試API端點
    if ! test_api_endpoints; then
        log_error "API端點測試失敗"
        cleanup_environment
        exit 1
    fi
    
    # 執行端到端測試
    if run_e2e_tests; then
        log_success "所有端到端測試執行成功"
        test_result=0
    else
        log_error "端到端測試執行失敗"
        test_result=1
    fi
    
    # 生成測試報告
    generate_test_report $test_result
    
    # 清理環境
    if [ "$keep_running" = true ]; then
        cleanup_environment "keep-running"
        log_info "Docker服務仍在運行，可以手動測試API"
        log_info "API端點: http://localhost/api"
        log_info "API文件: http://localhost/api/docs"
        log_info "Swagger UI: http://localhost/api/docs/swagger"
        log_info "停止服務: docker compose down"
    else
        cleanup_environment
    fi
    
    # 顯示最終結果
    echo "=========================================="
    if [ $test_result -eq 0 ]; then
        log_success "端到端系統測試全部通過！"
    else
        log_error "端到端系統測試失敗！"
    fi
    echo "=========================================="
    
    exit $test_result
}

# 執行主函數
main "$@"