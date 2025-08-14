#!/bin/bash

# Laravel 儲存目錄結構初始化腳本
# 確保所有必要的目錄都存在，避免部署時出現目錄不存在的錯誤

echo "=== Laravel 儲存目錄結構初始化 ==="

# 定義需要建立的目錄列表
directories=(
    "storage/app"
    "storage/app/public"
    "storage/framework"
    "storage/framework/cache"
    "storage/framework/cache/data"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
)

# 建立目錄並加入 .gitkeep 檔案
for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "建立目錄: $dir"
        mkdir -p "$dir"
    else
        echo "目錄已存在: $dir"
    fi
    
    # 為空目錄加入 .gitkeep 檔案
    if [ ! -f "$dir/.gitkeep" ] && [ -z "$(ls -A "$dir" 2>/dev/null)" ]; then
        echo "加入 .gitkeep 到: $dir"
        touch "$dir/.gitkeep"
    fi
done

# 設定適當的權限
echo ""
echo "設定目錄權限..."
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

echo ""
echo "✓ Laravel 儲存目錄結構初始化完成"
echo ""
echo "建立的目錄："
for dir in "${directories[@]}"; do
    echo "  - $dir"
done