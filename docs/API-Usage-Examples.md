# API使用範例和最佳實踐指南

## 概述

本指南提供Laravel統一API系統的完整使用範例，包含最佳實踐建議和常見使用場景的實作方式。

## 基本API呼叫

### 1. 基本請求格式

所有API請求都使用POST方法，發送到統一端點：

```bash
POST https://your-domain.com/api/
Content-Type: application/json
Authorization: Bearer your-token-here
```

請求主體格式：
```json
{
    "action_type": "action.name",
    "parameter1": "value1",
    "parameter2": "value2"
}
```

### 2. 使用cURL的範例

```bash
# 基本API呼叫
curl -X POST https://your-domain.com/api/ \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token-here" \
  -d '{
    "action_type": "user.info",
    "user_id": 123
  }'
```

### 3. 使用JavaScript的範例

```javascript
// 使用fetch API
async function callApi(actionType, data = {}) {
    const response = await fetch('https://your-domain.com/api/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer your-token-here'
        },
        body: JSON.stringify({
            action_type: actionType,
            ...data
        })
    });
    
    return await response.json();
}

// 使用範例
const userInfo = await callApi('user.info', { user_id: 123 });
console.log(userInfo);
```

### 4. 使用PHP的範例

```php
<?php

class ApiClient
{
    private $baseUrl;
    private $token;
    
    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }
    
    public function call(string $actionType, array $data = []): array
    {
        $payload = array_merge(['action_type' => $actionType], $data);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API呼叫失敗，HTTP狀態碼：{$httpCode}");
        }
        
        return json_decode($response, true);
    }
}

// 使用範例
$client = new ApiClient('https://your-domain.com', 'your-token-here');
$result = $client->call('user.info', ['user_id' => 123]);
```

## 常見使用場景

### 1. 使用者管理

#### 取得使用者資訊
```json
{
    "action_type": "user.info",
    "user_id": 123
}
```

回應：
```json
{
    "status": "success",
    "message": "使用者資料取得成功",
    "data": {
        "id": 123,
        "name": "張三",
        "email": "zhang@example.com",
        "created_at": "2024-01-01T00:00:00Z"
    }
}
```

#### 更新使用者資料
```json
{
    "action_type": "user.update",
    "user_id": 123,
    "name": "張三豐",
    "email": "zhangsan@example.com"
}
```

#### 取得使用者清單（分頁）
```json
{
    "action_type": "user.list",
    "page": 1,
    "per_page": 10,
    "search": "張",
    "sort_by": "created_at",
    "sort_order": "desc"
}
```

回應：
```json
{
    "status": "success",
    "message": "使用者清單取得成功",
    "data": [
        {
            "id": 123,
            "name": "張三",
            "email": "zhang@example.com"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 25,
        "last_page": 3,
        "has_more_pages": true
    }
}
```

### 2. 檔案上傳

```json
{
    "action_type": "file.upload",
    "file_data": "base64-encoded-file-content",
    "file_name": "document.pdf",
    "file_type": "application/pdf",
    "folder": "documents"
}
```

### 3. 系統資訊查詢

```json
{
    "action_type": "system.info"
}
```

回應：
```json
{
    "status": "success",
    "data": {
        "version": "1.0.0",
        "environment": "production",
        "server_time": "2024-01-01T12:00:00Z",
        "uptime": "7 days, 3 hours"
    }
}
```

## 錯誤處理

### 常見錯誤回應格式

#### 1. 驗證錯誤 (400)
```json
{
    "status": "error",
    "message": "請求參數驗證失敗",
    "error_code": "VALIDATION_ERROR",
    "details": {
        "email": ["電子郵件格式不正確"],
        "password": ["密碼長度至少8個字元"]
    }
}
```

#### 2. 認證錯誤 (401)
```json
{
    "status": "error",
    "message": "認證失敗，請檢查Bearer Token",
    "error_code": "AUTHENTICATION_FAILED"
}
```

#### 3. 權限不足 (403)
```json
{
    "status": "error",
    "message": "權限不足，無法執行此操作",
    "error_code": "INSUFFICIENT_PERMISSIONS"
}
```

#### 4. 資源不存在 (404)
```json
{
    "status": "error",
    "message": "指定的Action不存在",
    "error_code": "ACTION_NOT_FOUND"
}
```

#### 5. 系統錯誤 (500)
```json
{
    "status": "error",
    "message": "系統內部錯誤，請稍後再試",
    "error_code": "INTERNAL_SERVER_ERROR"
}
```

## 最佳實踐

### 1. 錯誤處理

```javascript
async function safeApiCall(actionType, data = {}) {
    try {
        const response = await callApi(actionType, data);
        
        if (response.status === 'success') {
            return response.data;
        } else {
            // 處理業務邏輯錯誤
            console.error('API錯誤：', response.message);
            throw new Error(response.message);
        }
    } catch (error) {
        // 處理網路錯誤或其他異常
        console.error('網路錯誤：', error.message);
        throw error;
    }
}
```

### 2. 重試機制

```javascript
async function apiCallWithRetry(actionType, data = {}, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            return await callApi(actionType, data);
        } catch (error) {
            if (attempt === maxRetries) {
                throw error;
            }
            
            // 指數退避
            const delay = Math.pow(2, attempt) * 1000;
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
}
```

### 3. 批次處理

```javascript
async function batchApiCalls(calls) {
    const results = [];
    const batchSize = 5; // 限制並發數量
    
    for (let i = 0; i < calls.length; i += batchSize) {
        const batch = calls.slice(i, i + batchSize);
        const batchResults = await Promise.allSettled(
            batch.map(call => callApi(call.actionType, call.data))
        );
        results.push(...batchResults);
    }
    
    return results;
}
```

### 4. 快取策略

```javascript
class ApiCache {
    constructor(ttl = 300000) { // 5分鐘TTL
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    getCacheKey(actionType, data) {
        return `${actionType}:${JSON.stringify(data)}`;
    }
    
    async get(actionType, data) {
        const key = this.getCacheKey(actionType, data);
        const cached = this.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < this.ttl) {
            return cached.data;
        }
        
        const result = await callApi(actionType, data);
        this.cache.set(key, {
            data: result,
            timestamp: Date.now()
        });
        
        return result;
    }
}
```

### 5. 請求攔截器

```javascript
class ApiInterceptor {
    constructor() {
        this.requestInterceptors = [];
        this.responseInterceptors = [];
    }
    
    addRequestInterceptor(interceptor) {
        this.requestInterceptors.push(interceptor);
    }
    
    addResponseInterceptor(interceptor) {
        this.responseInterceptors.push(interceptor);
    }
    
    async call(actionType, data = {}) {
        // 執行請求攔截器
        let requestData = { action_type: actionType, ...data };
        for (const interceptor of this.requestInterceptors) {
            requestData = await interceptor(requestData);
        }
        
        // 發送請求
        let response = await callApi(requestData.action_type, requestData);
        
        // 執行回應攔截器
        for (const interceptor of this.responseInterceptors) {
            response = await interceptor(response);
        }
        
        return response;
    }
}

// 使用範例
const api = new ApiInterceptor();

// 添加請求日誌
api.addRequestInterceptor(async (request) => {
    console.log('發送請求：', request);
    return request;
});

// 添加回應處理
api.addResponseInterceptor(async (response) => {
    if (response.status === 'error') {
        console.error('API錯誤：', response.message);
    }
    return response;
});
```

## 效能優化建議

### 1. 請求合併
```javascript
// 避免多次單獨請求
// 不好的做法
const user1 = await callApi('user.info', { user_id: 1 });
const user2 = await callApi('user.info', { user_id: 2 });

// 好的做法
const users = await callApi('user.batch_info', { user_ids: [1, 2] });
```

### 2. 分頁處理
```javascript
// 使用分頁避免一次載入大量資料
async function loadAllUsers() {
    const allUsers = [];
    let page = 1;
    let hasMore = true;
    
    while (hasMore) {
        const response = await callApi('user.list', {
            page: page,
            per_page: 50
        });
        
        allUsers.push(...response.data);
        hasMore = response.pagination.has_more_pages;
        page++;
    }
    
    return allUsers;
}
```

### 3. 條件請求
```javascript
// 使用條件參數減少不必要的資料傳輸
const response = await callApi('user.info', {
    user_id: 123,
    fields: ['id', 'name', 'email'], // 只取得需要的欄位
    include_permissions: false // 不包含權限資訊
});
```

## 安全性建議

### 1. Token管理
```javascript
class TokenManager {
    constructor() {
        this.token = null;
        this.refreshToken = null;
        this.tokenExpiry = null;
    }
    
    setToken(token, refreshToken, expiresIn) {
        this.token = token;
        this.refreshToken = refreshToken;
        this.tokenExpiry = Date.now() + (expiresIn * 1000);
    }
    
    async getValidToken() {
        if (this.isTokenExpired()) {
            await this.refreshAccessToken();
        }
        return this.token;
    }
    
    isTokenExpired() {
        return Date.now() >= this.tokenExpiry - 60000; // 提前1分鐘刷新
    }
    
    async refreshAccessToken() {
        // 實作Token刷新邏輯
    }
}
```

### 2. 輸入驗證
```javascript
function validateInput(data) {
    // 移除潛在的惡意內容
    const sanitized = {};
    
    for (const [key, value] of Object.entries(data)) {
        if (typeof value === 'string') {
            sanitized[key] = value.trim().replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        } else {
            sanitized[key] = value;
        }
    }
    
    return sanitized;
}
```

### 3. HTTPS使用
```javascript
// 確保所有API呼叫都使用HTTPS
const API_BASE_URL = process.env.NODE_ENV === 'production' 
    ? 'https://api.yourdomain.com' 
    : 'http://localhost:8000';
```

## 測試建議

### 1. 單元測試
```javascript
// Jest測試範例
describe('API Client', () => {
    test('應該成功呼叫user.info', async () => {
        const mockResponse = {
            status: 'success',
            data: { id: 123, name: '測試使用者' }
        };
        
        // Mock API呼叫
        jest.spyOn(global, 'fetch').mockResolvedValue({
            json: () => Promise.resolve(mockResponse)
        });
        
        const result = await callApi('user.info', { user_id: 123 });
        expect(result.status).toBe('success');
        expect(result.data.id).toBe(123);
    });
});
```

### 2. 整合測試
```javascript
describe('API Integration', () => {
    test('完整的使用者管理流程', async () => {
        // 建立使用者
        const createResult = await callApi('user.create', {
            name: '測試使用者',
            email: 'test@example.com'
        });
        
        expect(createResult.status).toBe('success');
        const userId = createResult.data.id;
        
        // 取得使用者資訊
        const getResult = await callApi('user.info', { user_id: userId });
        expect(getResult.data.name).toBe('測試使用者');
        
        // 更新使用者
        const updateResult = await callApi('user.update', {
            user_id: userId,
            name: '更新後的名稱'
        });
        expect(updateResult.status).toBe('success');
        
        // 刪除使用者
        const deleteResult = await callApi('user.delete', { user_id: userId });
        expect(deleteResult.status).toBe('success');
    });
});
```

這個指南提供了完整的API使用範例和最佳實踐，幫助開發者有效地使用Laravel統一API系統。