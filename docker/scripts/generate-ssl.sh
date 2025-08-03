#!/bin/bash

# SSL憑證生成腳本
# 用於開發環境的自簽名憑證

SSL_DIR="./docker/nginx/ssl"
DOMAIN="localhost"
COUNTRY="TW"
STATE="Taiwan"
CITY="Taipei"
ORGANIZATION="Unified API Server"
ORGANIZATIONAL_UNIT="Development"
EMAIL="admin@localhost"

# 建立SSL目錄
mkdir -p "$SSL_DIR"

echo "正在生成SSL憑證..."

# 生成私鑰
openssl genrsa -out "$SSL_DIR/server.key" 2048

# 生成憑證簽名請求
openssl req -new -key "$SSL_DIR/server.key" -out "$SSL_DIR/server.csr" -subj "/C=$COUNTRY/ST=$STATE/L=$CITY/O=$ORGANIZATION/OU=$ORGANIZATIONAL_UNIT/CN=$DOMAIN/emailAddress=$EMAIL"

# 生成自簽名憑證
openssl x509 -req -days 365 -in "$SSL_DIR/server.csr" -signkey "$SSL_DIR/server.key" -out "$SSL_DIR/server.crt" -extensions v3_req -extfile <(
cat <<EOF
[v3_req]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
DNS.2 = api.localhost
DNS.3 = *.localhost
IP.1 = 127.0.0.1
IP.2 = ::1
EOF
)

# 設定權限
chmod 600 "$SSL_DIR/server.key"
chmod 644 "$SSL_DIR/server.crt"

# 清理臨時檔案
rm "$SSL_DIR/server.csr"

echo "SSL憑證生成完成！"
echo "憑證位置: $SSL_DIR/server.crt"
echo "私鑰位置: $SSL_DIR/server.key"
echo ""
echo "注意：這是自簽名憑證，僅適用於開發環境。"
echo "生產環境請使用正式的SSL憑證。"