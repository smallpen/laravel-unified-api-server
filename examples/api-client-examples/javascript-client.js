/**
 * Laravel統一API系統 - JavaScript客戶端範例
 * 
 * 這個檔案提供了完整的JavaScript API客戶端實作範例，
 * 包含錯誤處理、重試機制、快取等功能。
 */

class LaravelApiClient {
    constructor(config = {}) {
        this.baseUrl = config.baseUrl || 'http://localhost:8000';
        this.token = config.token || null;
        this.timeout = config.timeout || 10000;
        this.retryAttempts = config.retryAttempts || 3;
        this.retryDelay = config.retryDelay || 1000;
        
        // 快取設定
        this.cacheEnabled = config.cacheEnabled || false;
        this.cacheTTL = config.cacheTTL || 300000; // 5分鐘
        this.cache = new Map();
        
        // 攔截器
        this.requestInterceptors = [];
        this.responseInterceptors = [];
    }

    /**
     * 設定Bearer Token
     */
    setToken(token) {
        this.token = token;
    }

    /**
     * 添加請求攔截器
     */
    addRequestInterceptor(interceptor) {
        this.requestInterceptors.push(interceptor);
    }

    /**
     * 添加回應攔截器
     */
    addResponseInterceptor(interceptor) {
        this.responseInterceptors.push(interceptor);
    }

    /**
     * 生成快取鍵
     */
    getCacheKey(actionType, data) {
        return `${actionType}:${JSON.stringify(data)}`;
    }

    /**
     * 檢查快取
     */
    getFromCache(key) {
        if (!this.cacheEnabled) return null;
        
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < this.cacheTTL) {
            return cached.data;
        }
        
        // 清除過期快取
        if (cached) {
            this.cache.delete(key);
        }
        
        return null;
    }

    /**
     * 設定快取
     */
    setCache(key, data) {
        if (!this.cacheEnabled) return;
        
        this.cache.set(key, {
            data: data,
            timestamp: Date.now()
        });
    }

    /**
     * 清除快取
     */
    clearCache(pattern = null) {
        if (pattern) {
            for (const key of this.cache.keys()) {
                if (key.includes(pattern)) {
                    this.cache.delete(key);
                }
            }
        } else {
            this.cache.clear();
        }
    }

    /**
     * 執行請求攔截器
     */
    async executeRequestInterceptors(requestData) {
        let data = requestData;
        for (const interceptor of this.requestInterceptors) {
            data = await interceptor(data);
        }
        return data;
    }

    /**
     * 執行回應攔截器
     */
    async executeResponseInterceptors(response) {
        let data = response;
        for (const interceptor of this.responseInterceptors) {
            data = await interceptor(data);
        }
        return data;
    }

    /**
     * 發送API請求
     */
    async call(actionType, data = {}, options = {}) {
        // 檢查快取
        const cacheKey = this.getCacheKey(actionType, data);
        const cachedResult = this.getFromCache(cacheKey);
        if (cachedResult) {
            console.log(`從快取取得結果: ${actionType}`);
            return cachedResult;
        }

        // 準備請求資料
        let requestData = {
            action_type: actionType,
            ...data
        };

        // 執行請求攔截器
        requestData = await this.executeRequestInterceptors(requestData);

        // 準備請求選項
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(this.token && { 'Authorization': `Bearer ${this.token}` }),
                ...options.headers
            },
            body: JSON.stringify(requestData),
            signal: options.signal
        };

        // 執行請求（含重試機制）
        let lastError;
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                console.log(`發送API請求 (嘗試 ${attempt}/${this.retryAttempts}): ${actionType}`);
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                
                requestOptions.signal = controller.signal;
                
                const response = await fetch(`${this.baseUrl}/api/`, requestOptions);
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                let result = await response.json();
                
                // 執行回應攔截器
                result = await this.executeResponseInterceptors(result);
                
                // 檢查API回應狀態
                if (result.status === 'error') {
                    throw new ApiError(result.message, result.error_code, result.details);
                }
                
                // 快取成功結果
                this.setCache(cacheKey, result);
                
                console.log(`API請求成功: ${actionType}`);
                return result;
                
            } catch (error) {
                lastError = error;
                console.warn(`API請求失敗 (嘗試 ${attempt}/${this.retryAttempts}): ${error.message}`);
                
                // 如果是最後一次嘗試或不可重試的錯誤，直接拋出
                if (attempt === this.retryAttempts || !this.shouldRetry(error)) {
                    break;
                }
                
                // 等待後重試
                await this.delay(this.retryDelay * Math.pow(2, attempt - 1));
            }
        }
        
        throw lastError;
    }

    /**
     * 判斷是否應該重試
     */
    shouldRetry(error) {
        // 網路錯誤或伺服器錯誤可以重試
        if (error.name === 'AbortError') return false; // 超時不重試
        if (error instanceof ApiError) {
            // 4xx錯誤通常不需要重試
            return false;
        }
        return true;
    }

    /**
     * 延遲函數
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * 批次API呼叫
     */
    async batchCall(calls, options = {}) {
        const concurrency = options.concurrency || 5;
        const results = [];
        
        for (let i = 0; i < calls.length; i += concurrency) {
            const batch = calls.slice(i, i + concurrency);
            const batchPromises = batch.map(call => 
                this.call(call.actionType, call.data, call.options)
                    .then(result => ({ success: true, result }))
                    .catch(error => ({ success: false, error }))
            );
            
            const batchResults = await Promise.all(batchPromises);
            results.push(...batchResults);
        }
        
        return results;
    }

    /**
     * 上傳檔案
     */
    async uploadFile(file, options = {}) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = async () => {
                try {
                    const base64Data = reader.result.split(',')[1];
                    
                    const result = await this.call('file.upload', {
                        file_data: base64Data,
                        file_name: file.name,
                        file_type: file.type,
                        folder: options.folder || 'uploads'
                    });
                    
                    resolve(result);
                } catch (error) {
                    reject(error);
                }
            };
            
            reader.onerror = () => reject(new Error('檔案讀取失敗'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * 分頁資料載入器
     */
    async *loadPages(actionType, baseData = {}, options = {}) {
        let page = 1;
        let hasMore = true;
        
        while (hasMore) {
            const result = await this.call(actionType, {
                ...baseData,
                page: page,
                per_page: options.perPage || 20
            });
            
            yield result.data;
            
            hasMore = result.data.pagination?.has_more_pages || false;
            page++;
            
            if (options.maxPages && page > options.maxPages) {
                break;
            }
        }
    }

    /**
     * 健康檢查
     */
    async healthCheck() {
        try {
            const result = await this.call('system.health');
            return result.data.status === 'healthy';
        } catch (error) {
            return false;
        }
    }
}

/**
 * API錯誤類別
 */
class ApiError extends Error {
    constructor(message, errorCode, details = null) {
        super(message);
        this.name = 'ApiError';
        this.errorCode = errorCode;
        this.details = details;
    }
}

/**
 * 使用範例
 */

// 基本使用
const apiClient = new LaravelApiClient({
    baseUrl: 'https://api.example.com',
    token: 'your-bearer-token',
    cacheEnabled: true,
    retryAttempts: 3
});

// 添加請求日誌攔截器
apiClient.addRequestInterceptor(async (request) => {
    console.log('發送請求:', request);
    return request;
});

// 添加回應處理攔截器
apiClient.addResponseInterceptor(async (response) => {
    if (response.status === 'error') {
        console.error('API錯誤:', response.message);
    }
    return response;
});

// 使用範例函數
async function examples() {
    try {
        // 1. 基本API呼叫
        const userInfo = await apiClient.call('user.info', { user_id: 123 });
        console.log('使用者資訊:', userInfo.data);

        // 2. 建立使用者
        const newUser = await apiClient.call('user.create', {
            name: '新使用者',
            email: 'new@example.com',
            password: 'password123',
            password_confirmation: 'password123'
        });
        console.log('新使用者:', newUser.data);

        // 3. 分頁資料載入
        const pageLoader = apiClient.loadPages('user.list', { search: '張' });
        for await (const pageData of pageLoader) {
            console.log('頁面資料:', pageData);
        }

        // 4. 批次API呼叫
        const batchCalls = [
            { actionType: 'user.info', data: { user_id: 1 } },
            { actionType: 'user.info', data: { user_id: 2 } },
            { actionType: 'user.info', data: { user_id: 3 } }
        ];
        const batchResults = await apiClient.batchCall(batchCalls);
        console.log('批次結果:', batchResults);

        // 5. 檔案上傳
        const fileInput = document.getElementById('file-input');
        if (fileInput.files.length > 0) {
            const uploadResult = await apiClient.uploadFile(fileInput.files[0], {
                folder: 'documents'
            });
            console.log('上傳結果:', uploadResult.data);
        }

        // 6. 健康檢查
        const isHealthy = await apiClient.healthCheck();
        console.log('系統健康狀態:', isHealthy);

    } catch (error) {
        if (error instanceof ApiError) {
            console.error('API錯誤:', error.message, error.errorCode, error.details);
        } else {
            console.error('網路錯誤:', error.message);
        }
    }
}

// 匯出供其他模組使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { LaravelApiClient, ApiError };
}

// 瀏覽器環境下的全域變數
if (typeof window !== 'undefined') {
    window.LaravelApiClient = LaravelApiClient;
    window.ApiError = ApiError;
}