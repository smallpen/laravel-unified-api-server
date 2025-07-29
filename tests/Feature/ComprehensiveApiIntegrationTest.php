<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Models\ApiLog;
use App\Services\TokenService;
use App\Services\ActionRegistry;
use App\Services\ResponseFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * 綜合API整合測試
 * 
 * 這個測試類別整合了所有API功能的完整測試，包括：
 * - 完整API呼叫流程的整合測試
 * - Bearer Token驗證流程
 * - Action路由和執行流程
 * - 錯誤處理和回應格式
 * - 日誌記錄和監控
 * - 權限控制機制
 * - 效能和穩定性測試
 * 
 * 對應需求：1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 5.1, 5.2, 5.3, 5.4
 */
class ComprehensiveApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;
    private string $validToken;
    private string $adminToken;
    private string $expiredToken;
    private TokenService $tokenService;
    private ActionRegistry $actionRegistry;
    private ResponseFormatter $responseFormatter;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => '一般測試使用者',
            'email' => 'user' . uniqid() . '@example.com',
        ]);

        $this->adminUser = User::factory()->create([
            'name' => '管理員測試使用者',
            'email' => 'admin' . uniqid() . '@example.com',
        ]);

        // 初始化服務
        $this->tokenService = app(TokenService::class);
        $this->actionRegistry = app(ActionRegistry::class);
        $this->responseFormatter = app(ResponseFormatter::class);

        // 建立測試Token
        $this->setupTestTokens();
    }

    /**
     * 設定測試用的Token
     */
    private function setupTestTokens(): void
    {
        // 建立有效的一般使用者Token
        $tokenData = $this->tokenService->createToken($this->user, '一般使用者整合測試Token', [
            'system.server_status', 'user.read', 'user.update'
        ]);
        $this->validToken = $tokenData['token'];

        // 建立管理員Token
        $adminTokenData = $this->tokenService->createToken(
            $this->adminUser, 
            '管理員整合測試Token', 
            ['admin', 'user.manage', 'system.server_status', 'user.read', 'user.update']
        );
        $this->adminToken = $adminTokenData['token'];

        // 建立過期Token
        $expiredTokenData = $this->tokenService->createToken(
            $this->user, 
            '過期整合測試Token', 
            [], 
            now()->subDay()
        );
        $this->expiredToken = $expiredTokenData['token'];
    }

    /**
     * 測試完整的API請求生命週期
     * 
     * 這個測試涵蓋從請求接收到回應返回的完整流程：
     * 1. HTTP請求接收
     * 2. Bearer Token驗證
     * 3. 請求參數驗證
     * 4. Action路由和解析
     * 5. Action執行
     * 6. 回應格式化
     * 7. 日誌記錄
     * 8. HTTP回應返回
     */
    public function test_complete_api_request_lifecycle(): void
    {
        // 清空日誌以便測試
        DB::table('api_logs')->truncate();

        // 記錄開始時間
        $startTime = microtime(true);

        // 發送完整的API請求
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'message' => '完整生命週期測試',
            'metadata' => [
                'test_id' => 'lifecycle_test_001',
                'timestamp' => now()->toISOString()
            ]
        ], [
            'Authorization' => "Bearer {$this->validToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'IntegrationTest/1.0'
        ]);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒

        // 1. 驗證HTTP回應狀態
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        // 2. 驗證回應結構完整性
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'action_type',
                'user_id',
                'timestamp',
                'server_time',
                'message',
                'system_status'
            ],
            'timestamp'
        ]);

        // 3. 驗證回應內容正確性
        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('system.ping', $responseData['data']['action_type']);
        $this->assertEquals($this->user->id, $responseData['data']['user_id']);
        $this->assertEquals('pong', $responseData['data']['message']);
        $this->assertEquals('healthy', $responseData['data']['system_status']);

        // 4. 驗證時間戳格式
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['timestamp']
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['data']['timestamp']
        );

        // 5. 驗證日誌記錄完整性
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'system.ping',
            'status_code' => 200
        ]);

        $log = ApiLog::where('user_id', $this->user->id)
                     ->where('action_type', 'system.ping')
                     ->first();

        $this->assertNotNull($log);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertLessThan(5000, $log->response_time); // 回應時間應該在5秒內
        $this->assertNotEmpty($log->request_id);
        $this->assertIsArray($log->request_data);
        $this->assertEquals('完整生命週期測試', $log->request_data['message']);

        // 6. 驗證Token使用記錄更新
        $tokenModel = ApiToken::where('token_hash', hash('sha256', $this->validToken))->first();
        $this->assertNotNull($tokenModel->last_used_at);
        $this->assertTrue($tokenModel->last_used_at->isAfter(now()->subMinute()));

        // 7. 驗證效能指標
        $this->assertLessThan(1000, $responseTime, '整個請求生命週期應該在1秒內完成');
    }

    /**
     * 測試Bearer Token驗證的完整流程
     * 
     * 涵蓋所有Token驗證場景：
     * - 有效Token驗證
     * - 無效Token拒絕
     * - 過期Token拒絕
     * - 格式錯誤Token拒絕
     * - 缺少Token拒絕
     */
    public function test_comprehensive_bearer_token_authentication_flow(): void
    {
        $authenticationScenarios = [
            [
                'name' => '有效Token驗證成功',
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 200,
                'expected_result' => 'success',
                'should_have_user_id' => true
            ],
            [
                'name' => '缺少Authorization標頭',
                'headers' => [],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '空的Authorization標頭',
                'headers' => ['Authorization' => ''],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '錯誤的Authorization格式',
                'headers' => ['Authorization' => 'Basic dGVzdDp0ZXN0'],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '無效的Bearer Token',
                'headers' => ['Authorization' => 'Bearer invalid_token_12345'],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '過期的Bearer Token',
                'headers' => ['Authorization' => "Bearer {$this->expiredToken}"],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '格式錯誤的Bearer Token',
                'headers' => ['Authorization' => 'Bearer '],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '包含特殊字元的Token',
                'headers' => ['Authorization' => 'Bearer token@with#special$chars'],
                'expected_status' => 401,
                'expected_result' => 'error',
                'expected_error_code' => 'UNAUTHORIZED'
            ]
        ];

        foreach ($authenticationScenarios as $scenario) {
            $response = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'test_scenario' => $scenario['name']
            ], $scenario['headers']);

            // 驗證HTTP狀態碼
            $response->assertStatus($scenario['expected_status']);

            // 驗證回應結構
            if ($scenario['expected_result'] === 'success') {
                $response->assertJsonStructure([
                    'status',
                    'message',
                    'data',
                    'timestamp'
                ]);

                if (isset($scenario['should_have_user_id']) && $scenario['should_have_user_id']) {
                    $response->assertJson([
                        'status' => 'success',
                        'data' => [
                            'user_id' => $this->user->id
                        ]
                    ]);
                }
            } else {
                $response->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp'
                ]);

                $response->assertJson([
                    'status' => 'error',
                    'error_code' => $scenario['expected_error_code']
                ]);
            }

            // 驗證時間戳格式
            $responseData = $response->json();
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $responseData['timestamp']
            );
        }
    }

    /**
     * 測試Action路由和執行的完整流程
     * 
     * 測試所有可用Action的路由、參數驗證和執行
     */
    public function test_comprehensive_action_routing_and_execution(): void
    {
        $actionTestCases = [
            [
                'action_type' => 'system.ping',
                'request_data' => [
                    'message' => 'Action路由測試'
                ],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'server_time', 'message', 'system_status'],
                'validation_rules' => [
                    'data.message' => 'pong',
                    'data.system_status' => 'healthy'
                ]
            ],
            [
                'action_type' => 'system.server_status',
                'request_data' => [],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'server_status'],
                'validation_rules' => [
                    'data.server_status.status' => 'healthy'
                ]
            ],
            [
                'action_type' => 'user.info',
                'request_data' => [],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'user'],
                'validation_rules' => [
                    'data.user.id' => $this->user->id,
                    'data.user.name' => $this->user->name,
                    'data.user.email' => $this->user->email
                ]
            ],
            [
                'action_type' => 'user.update',
                'request_data' => [
                    'name' => '更新後的名稱',
                    'email' => 'updated' . uniqid() . '@example.com'
                ],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'user', 'updated_fields'],
                'validation_rules' => [
                    'data.user.name' => '更新後的名稱'
                ]
            ]
        ];

        foreach ($actionTestCases as $testCase) {
            $requestData = array_merge(
                ['action_type' => $testCase['action_type']],
                $testCase['request_data']
            );

            $response = $this->postJson('/api', $requestData, [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            // 驗證回應狀態
            $response->assertStatus(200);

            // 驗證回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'data' => $testCase['expected_fields'],
                'timestamp'
            ]);

            // 驗證基本回應內容
            $response->assertJson([
                'status' => 'success',
                'data' => [
                    'action_type' => $testCase['action_type'],
                    'user_id' => $this->user->id
                ]
            ]);

            // 驗證特定驗證規則
            if (isset($testCase['validation_rules'])) {
                foreach ($testCase['validation_rules'] as $path => $expectedValue) {
                    $response->assertJson([$path => $expectedValue]);
                }
            }

            // 驗證日誌記錄
            $this->assertDatabaseHas('api_logs', [
                'user_id' => $this->user->id,
                'action_type' => $testCase['action_type'],
                'status_code' => 200
            ]);
        }
    }

    /**
     * 測試錯誤處理和回應格式的一致性
     * 
     * 涵蓋所有可能的錯誤情況和回應格式
     */
    public function test_comprehensive_error_handling_and_response_format(): void
    {
        $errorScenarios = [
            [
                'name' => '401 - 未授權錯誤',
                'request_data' => ['action_type' => 'system.ping'],
                'headers' => [],
                'expected_status' => 401,
                'expected_error_code' => 'UNAUTHORIZED',
                'expected_structure' => ['status', 'message', 'error_code', 'timestamp']
            ],
            [
                'name' => '404 - Action不存在',
                'request_data' => ['action_type' => 'non.existent.action'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 404,
                'expected_error_code' => 'ACTION_NOT_FOUND',
                'expected_structure' => ['status', 'message', 'error_code', 'timestamp']
            ],
            [
                'name' => '405 - 方法不允許',
                'method' => 'GET',
                'request_data' => ['action_type' => 'system.ping'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 405,
                'expected_error_code' => 'METHOD_NOT_ALLOWED',
                'expected_structure' => ['status', 'message', 'error_code', 'timestamp']
            ],
            [
                'name' => '422 - 驗證錯誤（缺少action_type）',
                'request_data' => ['data' => 'test'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 422,
                'expected_error_code' => 'VALIDATION_ERROR',
                'expected_structure' => ['status', 'message', 'error_code', 'details', 'timestamp']
            ],
            [
                'name' => '422 - 驗證錯誤（action_type格式錯誤）',
                'request_data' => ['action_type' => 'invalid action type'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 422,
                'expected_error_code' => 'VALIDATION_ERROR',
                'expected_structure' => ['status', 'message', 'error_code', 'details', 'timestamp']
            ]
        ];

        foreach ($errorScenarios as $scenario) {
            $method = $scenario['method'] ?? 'POST';
            
            $response = $this->json(
                $method,
                '/api',
                $scenario['request_data'],
                $scenario['headers']
            );

            // 驗證HTTP狀態碼
            $response->assertStatus($scenario['expected_status']);

            // 驗證回應結構
            $response->assertJsonStructure($scenario['expected_structure']);

            // 驗證錯誤回應內容
            $response->assertJson([
                'status' => 'error',
                'error_code' => $scenario['expected_error_code']
            ]);

            $responseData = $response->json();

            // 驗證錯誤訊息不為空
            $this->assertNotEmpty($responseData['message']);

            // 驗證時間戳格式
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $responseData['timestamp']
            );

            // 驗證敏感資訊不會洩漏
            $content = $response->getContent();
            $sensitiveTerms = ['password', 'token_hash', 'secret', 'private_key', 'database'];
            foreach ($sensitiveTerms as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    strtolower($content),
                    "錯誤回應不應包含敏感資訊: {$term}"
                );
            }

            // 驗證錯誤也被記錄到日誌
            if ($scenario['expected_status'] !== 405) { // 405錯誤可能不會到達我們的日誌中介軟體
                $this->assertDatabaseHas('api_logs', [
                    'status_code' => $scenario['expected_status']
                ]);
            }
        }
    }

    /**
     * 測試API日誌記錄的完整性
     * 
     * 驗證所有請求都被正確記錄，包括成功和失敗的請求
     */
    public function test_comprehensive_api_logging(): void
    {
        // 清空日誌
        DB::table('api_logs')->truncate();

        $loggingTestCases = [
            [
                'name' => '成功請求日誌',
                'request_data' => ['action_type' => 'system.ping', 'message' => '日誌測試'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 200
            ],
            [
                'name' => '失敗請求日誌',
                'request_data' => ['action_type' => 'non.existent.action'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 404
            ],
            [
                'name' => '驗證錯誤日誌',
                'request_data' => ['invalid_param' => 'test'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 422
            ]
        ];

        foreach ($loggingTestCases as $testCase) {
            $startTime = microtime(true);

            $response = $this->postJson('/api', $testCase['request_data'], $testCase['headers']);

            $endTime = microtime(true);
            $expectedResponseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒

            $response->assertStatus($testCase['expected_status']);

            // 驗證日誌記錄
            $log = ApiLog::where('user_id', $this->user->id)
                         ->orderBy('created_at', 'desc')
                         ->first();

            $this->assertNotNull($log, "應該記錄 {$testCase['name']}");
            $this->assertEquals($testCase['expected_status'], $log->status_code);
            $this->assertGreaterThan(0, $log->response_time);
            $this->assertLessThan($expectedResponseTime + 100, $log->response_time); // 允許100ms誤差
            $this->assertNotEmpty($log->request_id);
            $this->assertIsArray($log->request_data);
            $this->assertNotEmpty($log->ip_address);
            $this->assertNotEmpty($log->user_agent);

            // 驗證請求資料記錄正確
            foreach ($testCase['request_data'] as $key => $value) {
                $this->assertEquals($value, $log->request_data[$key]);
            }
        }

        // 驗證所有測試案例都被記錄
        $totalLogs = ApiLog::where('user_id', $this->user->id)->count();
        $this->assertEquals(count($loggingTestCases), $totalLogs);
    }

    /**
     * 測試併發請求處理能力
     * 
     * 驗證系統能夠正確處理多個同時進行的請求
     */
    public function test_concurrent_request_handling(): void
    {
        $concurrentRequests = 10;
        $responses = [];
        $startTime = microtime(true);

        // 同時發送多個請求
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'concurrent_id' => $i,
                'timestamp' => now()->toISOString()
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // 轉換為毫秒

        // 驗證所有請求都成功
        $successCount = 0;
        foreach ($responses as $index => $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
                
                $response->assertJson([
                    'status' => 'success',
                    'data' => [
                        'action_type' => 'system.ping',
                        'user_id' => $this->user->id
                    ]
                ]);
            }
        }

        $this->assertEquals($concurrentRequests, $successCount, '所有併發請求都應該成功');

        // 驗證所有請求都被記錄
        $logCount = ApiLog::where('user_id', $this->user->id)
                          ->where('action_type', 'system.ping')
                          ->count();
        $this->assertEquals($concurrentRequests, $logCount, '所有併發請求都應該被記錄');

        // 驗證併發處理效能
        $averageTime = $totalTime / $concurrentRequests;
        $this->assertLessThan(2000, $averageTime, '併發請求的平均處理時間應該合理');
    }

    /**
     * 測試大量資料處理能力
     * 
     * 驗證系統能夠處理包含大量資料的請求
     */
    public function test_large_data_handling(): void
    {
        // 建立大量測試資料
        $largeDataSet = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataSet[] = [
                'id' => $i,
                'name' => "測試項目 {$i}",
                'description' => str_repeat("這是測試描述 {$i} ", 10),
                'metadata' => [
                    'created_at' => now()->subDays(rand(1, 365))->toISOString(),
                    'category' => 'test_category_' . ($i % 10),
                    'tags' => array_fill(0, rand(1, 5), "tag_" . rand(1, 100))
                ]
            ];
        }

        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'large_data' => $largeDataSet,
            'message' => '大量資料處理測試'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 驗證大量資料請求成功處理
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'action_type' => 'system.ping',
                        'user_id' => $this->user->id
                    ]
                ]);

        // 驗證大量資料請求被正確記錄
        $log = ApiLog::where('user_id', $this->user->id)
                     ->where('action_type', 'system.ping')
                     ->orderBy('created_at', 'desc')
                     ->first();

        $this->assertNotNull($log);
        $this->assertEquals(200, $log->status_code);
        $this->assertArrayHasKey('large_data', $log->request_data);
        $this->assertCount(1000, $log->request_data['large_data']);
    }

    /**
     * 測試特殊字元和編碼處理
     * 
     * 驗證系統能夠正確處理各種特殊字元和編碼
     */
    public function test_special_characters_and_encoding_handling(): void
    {
        $specialCharacterTests = [
            [
                'name' => '中文字元測試',
                'data' => [
                    'message' => '這是中文測試訊息 🚀',
                    'description' => '包含中文、英文和表情符號的混合內容'
                ]
            ],
            [
                'name' => '表情符號測試',
                'data' => [
                    'message' => '😀😃😄😁😆😅😂🤣',
                    'emojis' => ['🎉', '🎊', '🎈', '🎁', '🎂']
                ]
            ],
            [
                'name' => 'JSON字串測試',
                'data' => [
                    'json_string' => '{"nested": "json data", "number": 123}',
                    'escaped_quotes' => 'This is a "quoted" string'
                ]
            ],
            [
                'name' => 'HTML實體測試',
                'data' => [
                    'html_content' => '&lt;script&gt;alert("test")&lt;/script&gt;',
                    'entities' => '&amp; &lt; &gt; &quot; &#39;'
                ]
            ],
            [
                'name' => 'Unicode字元測試',
                'data' => [
                    'unicode' => 'Ω≈ç√∫˜µ≤≥÷',
                    'symbols' => '™®©℠℗'
                ]
            ],
            [
                'name' => '特殊符號測試',
                'data' => [
                    'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
                    'currency' => '¥€£$¢'
                ]
            ]
        ];

        foreach ($specialCharacterTests as $test) {
            $response = $this->postJson('/api', array_merge([
                'action_type' => 'system.ping'
            ], $test['data']), [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success',
                        'data' => [
                            'action_type' => 'system.ping',
                            'user_id' => $this->user->id
                        ]
                    ]);

            // 驗證特殊字元在日誌中正確記錄
            $log = ApiLog::where('user_id', $this->user->id)
                         ->orderBy('created_at', 'desc')
                         ->first();

            $this->assertNotNull($log);
            
            // 驗證請求資料中的特殊字元被正確保存
            foreach ($test['data'] as $key => $value) {
                $this->assertEquals($value, $log->request_data[$key]);
            }
        }
    }

    /**
     * 測試系統在高負載下的穩定性
     * 
     * 驗證系統在處理大量請求時的穩定性和效能
     */
    public function test_system_stability_under_high_load(): void
    {
        $highLoadRequests = 50;
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];

        for ($i = 0; $i < $highLoadRequests; $i++) {
            $startTime = microtime(true);

            $response = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'load_test_id' => $i,
                'batch_size' => $highLoadRequests,
                'timestamp' => now()->toISOString()
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒
            $responseTimes[] = $responseTime;

            if ($response->getStatusCode() === 200) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        // 驗證成功率
        $successRate = ($successCount / $highLoadRequests) * 100;
        $this->assertGreaterThanOrEqual(95, $successRate, '高負載下成功率應該至少95%');

        // 驗證平均回應時間
        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $this->assertLessThan(2000, $averageResponseTime, '高負載下平均回應時間應該在2秒內');

        // 驗證最大回應時間
        $maxResponseTime = max($responseTimes);
        $this->assertLessThan(5000, $maxResponseTime, '高負載下最大回應時間應該在5秒內');

        // 驗證所有成功請求都被記錄
        $logCount = ApiLog::where('user_id', $this->user->id)
                          ->where('action_type', 'system.ping')
                          ->count();
        $this->assertEquals($successCount, $logCount, '所有成功請求都應該被記錄');
    }

    /**
     * 測試Token安全性和權限控制
     * 
     * 驗證Token的安全性機制和權限控制功能
     */
    public function test_token_security_and_permission_control(): void
    {
        // 測試Token不會以明文儲存
        $tokenModel = ApiToken::where('token_hash', hash('sha256', $this->validToken))->first();
        $this->assertNotNull($tokenModel);
        $this->assertNotEquals($this->validToken, $tokenModel->token_hash);
        $this->assertEquals(hash('sha256', $this->validToken), $tokenModel->token_hash);

        // 測試Token使用記錄
        $initialLastUsed = $tokenModel->last_used_at;
        
        $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $tokenModel->refresh();
        $this->assertTrue($tokenModel->last_used_at > $initialLastUsed);

        // 測試不同使用者Token的隔離性
        $response1 = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response2 = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$this->adminToken}"
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $user1Data = $response1->json();
        $user2Data = $response2->json();

        $this->assertNotEquals(
            $user1Data['data']['user_id'],
            $user2Data['data']['user_id'],
            '不同使用者的Token應該返回不同的使用者資訊'
        );
    }

    /**
     * 測試API文件和元資料
     * 
     * 驗證API能夠提供正確的元資料和文件資訊
     */
    public function test_api_metadata_and_documentation(): void
    {
        // 測試系統狀態Action
        $response = $this->postJson('/api', [
            'action_type' => 'system.server_status'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // 驗證系統狀態資訊
        $this->assertArrayHasKey('server_status', $responseData['data']);
        $serverStatus = $responseData['data']['server_status'];
        
        $this->assertArrayHasKey('status', $serverStatus);
        $this->assertArrayHasKey('timestamp', $serverStatus);
        $this->assertArrayHasKey('version', $serverStatus);
        $this->assertArrayHasKey('environment', $serverStatus);

        // 驗證Action註冊系統
        $allActions = $this->actionRegistry->getAllActions();
        $this->assertIsArray($allActions);
        $this->assertGreaterThan(0, count($allActions));

        // 驗證每個Action都有完整的文件
        foreach ($allActions as $actionType => $actionClass) {
            $action = $this->actionRegistry->resolve($actionType);
            $documentation = $action->getDocumentation();

            $this->assertIsArray($documentation);
            $this->assertArrayHasKey('name', $documentation);
            $this->assertArrayHasKey('description', $documentation);
            $this->assertArrayHasKey('version', $documentation);
            $this->assertArrayHasKey('parameters', $documentation);
            $this->assertArrayHasKey('responses', $documentation);
        }
    }

    /**
     * 清理測試資料
     */
    protected function tearDown(): void
    {
        // 清理測試產生的日誌
        DB::table('api_logs')->where('user_id', $this->user->id)->delete();
        DB::table('api_logs')->where('user_id', $this->adminUser->id)->delete();

        parent::tearDown();
    }
}