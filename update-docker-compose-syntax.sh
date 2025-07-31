#!/bin/bash

echo "=== 更新 Docker Compose 語法腳本 ==="

# 要更新的檔案列表
files_to_update=(
    "scripts/monitor.sh"
    "docs/System-Deployment-Guide.md"
)

# 備份原始檔案
echo "建立備份..."
for file in "${files_to_update[@]}"; do
    if [ -f "$file" ]; then
        cp "$file" "$file.backup"
        echo "✓ 已備份 $file"
    fi
done

# 更新 docker compose 命令為 docker compose
echo ""
echo "更新 Docker Compose 語法..."

# 使用 sed 批量替換
find . -type f \( -name "*.sh" -o -name "*.md" -o -name "*.yml" -o -name "*.yaml" \) \
    -not -path "./.git/*" \
    -not -path "./vendor/*" \
    -not -path "./node_modules/*" \
    -exec sed -i.bak 's/docker compose/docker compose/g' {} \;

# 清理備份檔案
find . -name "*.bak" -not -path "./.git/*" -delete

echo "✓ 已更新所有檔案中的 Docker Compose 語法"

# 特殊處理：docker compose.yml 檔案名稱不需要更改
echo ""
echo "注意：docker compose.yml 檔案名稱保持不變（這是標準檔案名）"

echo ""
echo "=== 更新完成 ==="
echo "請檢查更新後的檔案，確認語法正確"