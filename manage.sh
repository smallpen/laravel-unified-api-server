#!/bin/bash

# 統一API Server管理腳本

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 顯示使用說明
show_help() {
    echo "統一API Server管理腳本"
    echo ""
    echo "使用方法: $0 <命令> [選項]"
    echo ""
    echo "可用命令："
    echo "  start [環境]          - 啟動服務 (預設: development)"
    echo "  stop [環境] [清理]    - 停止服務 (清理: true/false)"
    echo "  restart [環境]        - 重新啟動服務"
    echo "  rebuild [環境]        - 重建並啟動服務"
    echo "  status [環境]         - 檢查服務狀態"
    echo "  logs [環境] [服務]    - 查看日誌"
    echo "  backup [環境]         - 建立備份"
    echo "  restore <檔案> [環境] - 還原備份"
    echo "  ssl                   - 生成SSL憑證"
    echo "  env                   - 設定環境變數"
    echo "  help                  - 顯示此說明"
    echo ""
    echo "環境選項："
    echo "  development (預設)    - 開發環境"
    echo "  production           - 生產環境"
    echo ""
    echo "範例："
    echo "  $0 start                    # 啟動開發環境"
    echo "  $0 start production         # 啟動生產環境"
    echo "  $0 stop development true    # 停止開發環境並清理資料"
    echo "  $0 logs development nginx   # 查看開發環境Nginx日誌"
    echo "  $0 backup production        # 備份生產環境"
}

# 檢查Docker和Docker Compose是否安裝
check_requirements() {
    if ! command -v docker &> /dev/null; then
        echo "錯誤: Docker未安裝或不在PATH中"
        exit 1
    fi
    
    if ! docker compose version &> /dev/null; then
        echo "錯誤: Docker Compose未安裝或不在PATH中"
        echo "請確保已安裝 Docker Desktop 或 Docker Engine with Compose plugin"
        exit 1
    fi
}

# 主要邏輯
main() {
    local command="$1"
    
    case "$command" in
        "start")
            check_requirements
            ./docker/scripts/start.sh "${2:-development}"
            ;;
        "stop")
            check_requirements
            ./docker/scripts/stop.sh "${2:-development}" "${3:-false}"
            ;;
        "restart")
            check_requirements
            echo "重新啟動統一API Server..."
            ./docker/scripts/stop.sh "${2:-development}"
            sleep 5
            ./docker/scripts/start.sh "${2:-development}"
            ;;
        "rebuild")
            check_requirements
            ./docker/scripts/rebuild.sh "${2:-development}"
            ;;
        "status"|"health")
            check_requirements
            ./docker/scripts/health-check.sh "${2:-development}"
            ;;
        "logs")
            check_requirements
            ./docker/scripts/logs.sh "${2:-development}" "${3:-all}" "${4:-100}"
            ;;
        "backup")
            check_requirements
            ./docker/scripts/backup.sh "${2:-development}"
            ;;
        "restore")
            if [ -z "$2" ]; then
                echo "錯誤: 請指定備份檔案"
                echo "使用方法: $0 restore <備份檔案> [環境]"
                exit 1
            fi
            check_requirements
            ./docker/scripts/restore.sh "$2" "${3:-development}"
            ;;
        "ssl")
            ./docker/scripts/generate-ssl.sh
            ;;
        "env")
            ./docker/scripts/setup-env.sh
            ;;
        "help"|"-h"|"--help"|"")
            show_help
            ;;
        *)
            echo "錯誤: 未知命令 '$command'"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# 執行主要邏輯
main "$@"