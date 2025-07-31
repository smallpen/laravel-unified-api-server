# 貢獻指南

感謝您對Laravel統一API系統的關注！我們歡迎所有形式的貢獻，包括錯誤報告、功能建議、文件改進和程式碼貢獻。

## 📋 目錄

- [行為準則](#行為準則)
- [如何貢獻](#如何貢獻)
- [開發環境設定](#開發環境設定)
- [提交指南](#提交指南)
- [程式碼規範](#程式碼規範)
- [測試要求](#測試要求)
- [文件要求](#文件要求)
- [發布流程](#發布流程)

## 行為準則

本專案採用 [Contributor Covenant](https://www.contributor-covenant.org/) 行為準則。參與此專案即表示您同意遵守其條款。

### 我們的承諾

為了營造開放且友善的環境，我們承諾：

- 使用友善和包容的語言
- 尊重不同的觀點和經驗
- 優雅地接受建設性批評
- 專注於對社群最有利的事情
- 對其他社群成員表現同理心

## 如何貢獻

### 🐛 報告錯誤

在報告錯誤之前，請：

1. 檢查 [現有Issues](https://github.com/your-username/laravel-unified-api-server/issues) 確認問題尚未被報告
2. 確保您使用的是最新版本
3. 查看 [故障排除指南](docs/Troubleshooting-FAQ.md)

#### 錯誤報告範本

```markdown
**描述錯誤**
簡潔清楚地描述錯誤。

**重現步驟**
1. 執行 '...'
2. 點擊 '....'
3. 滾動到 '....'
4. 看到錯誤

**預期行為**
描述您預期應該發生什麼。

**實際行為**
描述實際發生了什麼。

**環境資訊**
- OS: [例如 Ubuntu 20.04]
- PHP版本: [例如 8.1.0]
- Laravel版本: [例如 10.0.0]
- Docker版本: [例如 20.10.0]

**額外資訊**
添加任何其他相關的截圖或資訊。
```

### 💡 建議功能

我們歡迎功能建議！請：

1. 檢查是否已有類似建議
2. 詳細描述功能和使用場景
3. 考慮功能的實作複雜度
4. 提供具體的使用範例

### 📝 改進文件

文件改進包括：

- 修正錯字或語法錯誤
- 改善說明的清晰度
- 添加使用範例
- 翻譯文件到其他語言

### 💻 程式碼貢獻

1. Fork 此專案
2. 建立功能分支 (`git checkout -b feature/amazing-feature`)
3. 進行變更
4. 添加測試
5. 確保所有測試通過
6. 提交變更 (`git commit -m 'Add amazing feature'`)
7. 推送到分支 (`git push origin feature/amazing-feature`)
8. 開啟 Pull Request

## 開發環境設定

### 前置需求

- Docker 20.10+
- Docker Compose 2.0+
- Git 2.30+

### 設定步驟

1. **複製專案**
```bash
git clone https://github.com/your-username/laravel-unified-api-server.git
cd laravel-unified-api-server
```

2. **設定環境**
```bash
cp .env.example .env
```

3. **啟動開發環境**
```bash
./manage.sh start development
```

4. **安裝依賴**
```bash
docker compose exec laravel composer install
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
```

5. **執行測試**
```bash
docker compose exec laravel php artisan test
```

## 提交指南

### 提交訊息格式

我們使用 [Conventional Commits](https://www.conventionalcommits.org/) 格式：

```
<類型>[可選範圍]: <描述>

[可選正文]

[可選頁腳]
```

#### 類型

- `feat`: 新功能
- `fix`: 錯誤修正
- `docs`: 文件變更
- `style`: 程式碼格式變更（不影響功能）
- `refactor`: 程式碼重構
- `test`: 測試相關變更
- `chore`: 建置過程或輔助工具變更

#### 範例

```bash
feat(auth): add Bearer token validation
fix(api): resolve user permission check issue
docs(readme): update installation instructions
test(unit): add tests for Action permission system
```

### Pull Request 指南

#### PR標題格式

使用與提交訊息相同的格式：

```
feat(auth): add Bearer token validation
```

#### PR描述範本

```markdown
## 變更類型
- [ ] 錯誤修正
- [ ] 新功能
- [ ] 程式碼重構
- [ ] 文件更新
- [ ] 測試改進

## 變更描述
簡潔描述此PR的變更內容。

## 相關Issue
關閉 #123

## 測試
- [ ] 單元測試通過
- [ ] 功能測試通過
- [ ] 手動測試完成

## 檢查清單
- [ ] 程式碼遵循專案規範
- [ ] 自我審查程式碼
- [ ] 添加必要的註解
- [ ] 更新相關文件
- [ ] 添加對應測試
- [ ] 所有測試通過
```

## 程式碼規範

### PHP程式碼規範

我們遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 程式碼規範：

- 使用4個空格縮排
- 行長度限制為120字元
- 類別名稱使用 PascalCase
- 方法名稱使用 camelCase
- 常數使用 UPPER_CASE

### Laravel特定規範

- 控制器方法使用 camelCase
- 路由使用 kebab-case
- 資料庫表名使用 snake_case
- 模型屬性使用 snake_case

### 程式碼品質工具

```bash
# 程式碼格式檢查
docker compose exec laravel ./vendor/bin/php-cs-fixer fix --dry-run

# 程式碼格式修正
docker compose exec laravel ./vendor/bin/php-cs-fixer fix

# 靜態分析
docker compose exec laravel ./vendor/bin/phpstan analyse
```

## 測試要求

### 測試類型

1. **單元測試**: 測試個別類別和方法
2. **功能測試**: 測試完整的API流程
3. **整合測試**: 測試系統組件間的整合

### 測試覆蓋率

- 新功能必須有對應的測試
- 測試覆蓋率應保持在80%以上
- 關鍵業務邏輯必須有100%覆蓋率

### 執行測試

```bash
# 執行所有測試
docker compose exec laravel php artisan test

# 執行特定測試套件
docker compose exec laravel php artisan test --testsuite=Unit

# 生成覆蓋率報告
docker compose exec laravel php artisan test --coverage
```

### 測試命名規範

```php
// 單元測試
public function test_can_create_user_with_valid_data()
public function test_throws_exception_when_email_is_invalid()

// 功能測試
public function test_user_can_login_with_correct_credentials()
public function test_api_returns_error_for_invalid_token()
```

## 文件要求

### 程式碼註解

- 所有公開方法必須有PHPDoc註解
- 複雜邏輯需要內聯註解說明
- 使用正體中文撰寫註解

```php
/**
 * 建立新的使用者帳號
 *
 * @param array $data 使用者資料
 * @return User 建立的使用者物件
 * @throws ValidationException 當資料驗證失敗時
 */
public function createUser(array $data): User
{
    // 驗證使用者資料
    $this->validateUserData($data);
    
    // 建立使用者
    return User::create($data);
}
```

### API文件

- 所有Action必須實作 `getDocumentation()` 方法
- 提供完整的參數說明和範例
- 包含錯誤回應格式

### README更新

當添加新功能時，請更新：

- 功能列表
- 使用範例
- 配置說明

## 發布流程

### 版本號規則

我們使用 [語義化版本](https://semver.org/)：

- `MAJOR.MINOR.PATCH`
- 例如：`1.2.3`

### 發布檢查清單

- [ ] 所有測試通過
- [ ] 文件已更新
- [ ] CHANGELOG.md已更新
- [ ] 版本號已更新
- [ ] 建立Git標籤
- [ ] 發布Release Notes

### 發布命令

```bash
# 建立版本標籤
git tag -a v1.0.0 -m "Release version 1.0.0"

# 推送標籤
git push origin v1.0.0
```

## 🤝 社群

### 聯絡方式

- **GitHub Issues**: 錯誤報告和功能建議
- **Email**: admin@your-domain.com
- **文件**: [完整文件](docs/README.md)

### 貢獻者

感謝所有為此專案做出貢獻的開發者！

## 📄 授權

通過貢獻此專案，您同意您的貢獻將在 [MIT授權](LICENSE) 下授權。

---

再次感謝您的貢獻！每一個貢獻都讓這個專案變得更好。