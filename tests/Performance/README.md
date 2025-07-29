# 效能和安全測試套件

本目錄包含了Laravel統一API Server的效能和安全測試。

## 效能測試 (Performance Tests)

### ApiLoadTest.php
測試API在各種負載情況下的效能表現：

- **test_single_api_request_performance**: 測試單一API請求的基準效能
- **test_consecutive_api_requests_performance**: 測試連續API請求的效能
- **test_database_query_performance**: 測試資料庫查詢效能
- **test_memory_usage**: 測試記憶體使用量
- **test_cache_performance**: 測試快取效能
- **test_concurrent_request_handling**: 測試併發請求處理能力
- **test_token_validation_performance**: 測試Token驗證效能
- **test_large_data_processing_performance**: 測試大量資料處理效能

### 執行效能測試
```bash
php artisan test --testsuite=Performance
```

## 安全測試 (Security Tests)

### TokenSecurityTest.php
測試API Token的各種安全機制：

- **test_token_hash_security**: 測試Token的雜湊安全性
- **test_token_expiration_security**: 測試Token過期機制
- **test_token_revocation_security**: 測試Token撤銷機制
- **test_token_permission_isolation**: 測試Token權限隔離
- **test_token_brute_force_protection**: 測試Token暴力破解防護
- **test_token_timing_attack_protection**: 測試Token時間攻擊防護
- **test_token_replay_attack_protection**: 測試Token重放攻擊防護
- **test_token_leakage_protection**: 測試Token洩漏防護
- **test_token_strength**: 測試Token長度和複雜度
- **test_token_cleanup_mechanism**: 測試Token清理機制
- **test_token_privilege_escalation_protection**: 測試Token權限升級防護

### InputValidationSecurityTest.php
測試系統對各種惡意輸入的防護能力：

- **test_sql_injection_protection**: 測試SQL注入防護
- **test_xss_protection**: 測試XSS防護
- **test_command_injection_protection**: 測試命令注入防護
- **test_path_traversal_protection**: 測試路徑遍歷攻擊防護
- **test_ldap_injection_protection**: 測試LDAP注入防護
- **test_nosql_injection_protection**: 測試NoSQL注入防護
- **test_large_input_protection**: 測試大型輸入攻擊防護
- **test_special_character_handling**: 測試特殊字元處理
- **test_encoding_attack_protection**: 測試編碼攻擊防護
- **test_json_injection_protection**: 測試JSON注入防護
- **test_parameter_pollution_protection**: 測試參數污染攻擊防護
- **test_database_query_log_security**: 測試資料庫查詢日誌安全性

### PermissionBypassSecurityTest.php
測試系統對各種權限繞過攻擊的防護能力：

- **test_token_substitution_attack_protection**: 測試Token替換攻擊防護
- **test_privilege_escalation_attack_protection**: 測試權限提升攻擊防護
- **test_session_hijacking_protection**: 測試Session劫持防護
- **test_csrf_attack_protection**: 測試CSRF攻擊防護
- **test_parameter_tampering_protection**: 測試參數篡改攻擊防護
- **test_timing_attack_protection**: 測試時間攻擊防護
- **test_permission_cache_bypass_protection**: 測試權限快取繞過攻擊防護
- **test_concurrent_permission_check_protection**: 測試並發權限檢查攻擊防護
- **test_token_replay_attack_protection**: 測試Token重放攻擊防護
- **test_permission_boundary_attack_protection**: 測試權限邊界攻擊防護
- **test_permission_inheritance_attack_protection**: 測試權限繼承攻擊防護
- **test_permission_cache_pollution_protection**: 測試權限快取污染攻擊防護

### 執行安全測試
```bash
php artisan test --testsuite=Security
```

## 負載測試腳本

### scripts/load-test.php
獨立的負載測試腳本，可用於實際環境的壓力測試：

```bash
php scripts/load-test.php --url=http://localhost/api/ --token=your_token --concurrent=10 --requests=100
```

參數說明：
- `--url`: API端點URL
- `--token`: Bearer Token
- `--concurrent`: 併發使用者數量
- `--requests`: 每使用者請求數量

## 測試結果解讀

### 效能指標
- **回應時間**: 單次請求的處理時間
- **吞吐量**: 每秒處理的請求數量 (RPS)
- **記憶體使用**: 系統記憶體消耗
- **併發處理**: 同時處理多個請求的能力

### 安全指標
- **輸入驗證**: 對惡意輸入的防護能力
- **權限控制**: 權限檢查和隔離機制
- **Token安全**: Token的生成、驗證和管理安全性
- **攻擊防護**: 對各種攻擊手法的防護能力

## 注意事項

1. **測試環境**: 這些測試設計用於開發和測試環境，不應在生產環境中執行
2. **資料庫**: 安全測試會建立和刪除測試資料，請確保使用測試資料庫
3. **效能基準**: 效能測試的基準值可能需要根據實際硬體環境調整
4. **安全更新**: 隨著新的安全威脅出現，應定期更新和擴展安全測試

## 持續改進

建議定期執行這些測試，並根據測試結果：

1. 優化系統效能瓶頸
2. 加強安全防護機制
3. 更新測試案例以涵蓋新的威脅
4. 調整效能基準值以反映系統改進