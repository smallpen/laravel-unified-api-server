# 正體中文設定指南

## 語言偏好

請在所有互動中使用正體中文（繁體中文），包括：

1. 程式碼註解和說明
2. API 文檔和技術文件
3. 錯誤訊息和除錯資訊
4. 使用者介面文字和回應

## 技術術語翻譯指南

請使用台灣地區常用的技術術語翻譯，例如：

- "函數" 而非 "函数"
- "物件" 而非 "对象"
- "陣列" 而非 "数组"
- "變數" 而非 "变量"
- "類別" 而非 "类"
- "方法" 而非 "方法"（相同）
- "屬性" 而非 "属性"
- "介面" 而非 "接口"
- "實作" 而非 "实现"
- "繼承" 而非 "继承"
- "例外" 而非 "异常"
- "執行緒" 而非 "线程"
- "事件" 而非 "事件"（相同）
- "監聽器" 而非 "监听器"
- "回呼" 而非 "回调"
- "同步" 而非 "同步"（相同）
- "非同步" 而非 "异步"
- "承諾" 而非 "承诺"（Promise）
- "解析" 而非 "解析"（相同）
- "拒絕" 而非 "拒绝"（Reject）

## 程式碼註解格式

在生成程式碼時，請使用以下格式的正體中文註解：

### JavaScript/TypeScript
```javascript
/**
 * 這個函數用於處理使用者輸入
 * @param {string} input - 使用者輸入的字串
 * @returns {Object} 處理後的結果物件
 */
function processInput(input) {
  // 檢查輸入是否為空
  if (!input) {
    return { error: '輸入不能為空' };
  }
  
  // 處理輸入並回傳結果
  return { result: input.trim() };
}
```

### Python
```python
def process_input(input):
    """
    這個函數用於處理使用者輸入
    
    參數:
        input (str): 使用者輸入的字串
        
    回傳:
        dict: 處理後的結果字典
    """
    # 檢查輸入是否為空
    if not input:
        return {"error": "輸入不能為空"}
    
    # 處理輸入並回傳結果
    return {"result": input.strip()}
```

### Java
```java
/**
 * 這個方法用於處理使用者輸入
 *
 * @param input 使用者輸入的字串
 * @return 處理後的結果物件
 */
public Result processInput(String input) {
    // 檢查輸入是否為空
    if (input == null || input.isEmpty()) {
        return new Result("輸入不能為空");
    }
    
    // 處理輸入並回傳結果
    return new Result(input.trim());
}
```

## 文檔格式

在生成文檔時，請使用以下格式的正體中文：

### README.md
```markdown
# 專案名稱

## 簡介
這個專案旨在提供...

## 安裝
```bash
npm install 專案名稱
```

## 使用方法
```javascript
const package = require('專案名稱');
package.doSomething();
```

## API 文檔
### function doSomething()
這個函數用於...

#### 參數
- `param1` (String): 第一個參數的說明
- `param2` (Number): 第二個參數的說明

#### 回傳值
- (Object): 回傳值的說明

## 貢獻指南
歡迎提交 Pull Request 或建立 Issue。
```

## 錯誤訊息格式

請使用清晰、具體的正體中文錯誤訊息，例如：

- "找不到指定的檔案 'config.json'"
- "無法連接到資料庫，請檢查連線設定"
- "使用者輸入格式不正確，請提供有效的電子郵件地址"
- "操作逾時，請稍後再試"
- "權限不足，無法存取此資源"

## 變數和函數命名

雖然程式碼中的變數名和函數名應保持使用英文（遵循程式設計慣例），但在註解和文檔中應使用正體中文解釋其用途和功能。