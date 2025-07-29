<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Models\ApiLog;
use App\Services\TokenService;
use App\Services\ActionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 完整API整合測試
 * 
 * 測試完整的API呼叫流程，包括：
 * - Bearer Token驗證流程
 * - Action路由和執行流程
 * - 錯誤處理和回應格式
 * - 日誌記錄功能
 * - 權限控制機制
 */
class CompleteApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;
    private string $validToken;
    private string $adminToken;
    private string $expiredToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立一般測試使用者（使用隨機email避免重複）
        $this->user = User::factory()->create([
            'name' => '一般使用者',
            'email' => 'user' . uniqid() . '@example.com',
        ]);

        // 建立管理員使用者
        $this->adminUser = User::factory()->create([
            'name' => '管理員使用者',
            'email' => 'admin' . uniqid() . '@example.com',
        ]);

        // 建立有效的API Token
        $tokenService = app(TokenService::class);
        $tokenData = $tokenService->createToken($this->user, '一般使用者Token', [
            'system.server_status', 'user.read', 'user.update'
        ]);
        $this->validToken = $tokenData['token'];

        // 建立管理員Token
        $adminTokenData = $tokenService->createToken($this->adminUser, '管理員Token', [
            'admin', 'system.server_status', 'user.read', 'user.update'
        ]);
        $this->adminToken = $adminTokenData['token'];

        // 建立過期Token
        $expiredTokenData = $tokenService->createToken(
            $this->user, 
            '過期Token', 
            [], 
            now()->subDay()
        );
        $this->expiredToken = $expiredTokenData['token'];
    }

    /**
     * 測試完整的成功API請求流程
     * 
     * 涵蓋：Bearer Token驗證 -> Action路由 -> 執行 -> 回應格式化
     */
    public function test_complete_successful_api_request_flow(): void
    {
        // 清空日誌以便測試
        DB::table('api_logs')->truncate();

        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'message' => '測試訊息'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 驗證HTTP狀態碼
        $response->assertStatus(200);

        // 驗證回應結構
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'message',
                'timestamp',
                'server_time',
                'user_id',
                'system_status'
            ],
            'timestamp'
        ]);

        // 驗證回應內容
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'message' => 'pong',
                'user_id' => $this->user->id,
                'system_status' => 'healthy'
            ]
        ]);

        // 驗證時間戳格式
        $data = $response->json();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $data['timestamp']
        );

        // 驗證API日誌記錄
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'system.ping',
            'status_code' => 200
        ]);

        $log = ApiLog::where('user_id', $this->user->id)->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertNotEmpty($log->request_id);
        $this->assertEquals(['action_type' => 'system.ping', 'message' => '測試訊息'], $log->request_data);
    }

    /**
     * 測試Bearer Token驗證流程的各種情況
     */
    public function test_bearer_token_authentication_scenarios(): void
    {
        // 測試1: 沒有Authorization標頭
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試2: 格式錯誤的Authorization標頭
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Basic dGVzdDp0ZXN0'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試3: 無效的Bearer Token
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer invalid_token_12345'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試4: 過期的Bearer Token
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$this->expiredToken}"
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試5: 有效的Bearer Token
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);
    }

    /**
     * 測試Action路由和執行流程
     */
    public function test_action_routing_and_execution_flow(): void
    {
        // 測試所有可用的Action類型
        $availableActions = [
            'system.ping' => [],
            'system.server_status' => [],
            'user.info' => [],
            'user.update' => ['name' => '新名稱', 'email' => 'new' . uniqid() . '@example.com']
        ];

        foreach ($availableActions as $actionType => $requestData) {
            $requestData['action_type'] = $actionType;
            
            $response = $this->postJson('/api', $requestData, [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success'
                    ]);
            
            // 驗證回應包含使用者ID（在不同Action中位置可能不同）
            $responseData = $response->json();
            $this->assertArrayHasKey('data', $responseData);
            
            // 根據不同Action類型驗證特定欄位
            if ($actionType === 'system.ping') {
                $this->assertArrayHasKey('user_id', $responseData['data']);
                $this->assertEquals($this->user->id, $responseData['data']['user_id']);
            } elseif ($actionType === 'user.info') {
                $this->assertArrayHasKey('user', $responseData['data']);
                $this->assertEquals($this->user->id, $responseData['data']['user']['id']);
            } elseif ($actionType === 'user.update') {
                $this->assertArrayHasKey('user', $responseData['data']);
                $this->assertEquals($this->user->id, $responseData['data']['user']['id']);
            } elseif ($actionType === 'system.server_status') {
                $this->assertArrayHasKey('server_status', $responseData['data']);
            }

            // 驗證每個Action都有正確的回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'data',
                'timestamp'
            ]);
        }
    }

    /**
     * 測試Action不存在的情況
     */
    public function test_non_existent_action_handling(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'non.existent.action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'ACTION_NOT_FOUND'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp'
                ]);

        // 驗證錯誤也會被記錄
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'non.existent.action',
            'status_code' => 404
        ]);
    }

    /**
     * 測試請求參數驗證
     */
    public function test_request_parameter_validation(): void
    {
        // 測試1: 缺少action_type參數
        $response = $this->postJson('/api', [
            'data' => ['test' => 'value']
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR'
                ]);
        
        // 檢查回應中是否包含驗證錯誤詳情
        $responseData = $response->json();
        $this->assertArrayHasKey('details', $responseData);
        $this->assertArrayHasKey('action_type', $responseData['details']);

        // 測試2: action_type格式錯誤
        $invalidActionTypes = [
            'action with spaces',
            'action@invalid',
            '',
            str_repeat('a', 101) // 超過100字元
        ];

        foreach ($invalidActionTypes as $invalidActionType) {
            $response = $this->postJson('/api', [
                'action_type' => $invalidActionType
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(422)
                    ->assertJson([
                        'status' => 'error',
                        'error_code' => 'VALIDATION_ERROR'
                    ]);
        }

        // 測試3: Action特定參數驗證（以user.update為例）
        $response = $this->postJson('/api', [
            'action_type' => 'user.update',
            'email' => 'invalid-email-format'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR'
                ]);
    }

    /**
     * 測試HTTP方法限制
     */
    public function test_http_method_restrictions(): void
    {
        $methods = ['GET', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $response = $this->json($method, '/api', [
                'action_type' => 'system.ping'
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(405)
                    ->assertJson([
                        'status' => 'error',
                        'error_code' => 'METHOD_NOT_ALLOWED'
                    ]);
        }
    }

    /**
     * 測試錯誤處理和回應格式一致性
     */
    public function test_error_handling_and_response_format_consistency(): void
    {
        $errorScenarios = [
            // 401 錯誤
            [
                'request' => ['action_type' => 'system.ping'],
                'headers' => [],
                'expected_status' => 401,
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            // 404 錯誤
            [
                'request' => ['action_type' => 'non.existent'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 404,
                'expected_error_code' => 'ACTION_NOT_FOUND'
            ],
            // 422 驗證錯誤
            [
                'request' => ['action_type' => ''],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 422,
                'expected_error_code' => 'VALIDATION_ERROR'
            ]
        ];

        foreach ($errorScenarios as $scenario) {
            $response = $this->postJson('/api', $scenario['request'], $scenario['headers']);

            $response->assertStatus($scenario['expected_status'])
                    ->assertJson([
                        'status' => 'error',
                        'error_code' => $scenario['expected_error_code']
                    ])
                    ->assertJsonStructure([
                        'status',
                        'message',
                        'error_code',
                        'timestamp'
                    ]);

            // 驗證錯誤回應的時間戳格式
            $data = $response->json();
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $data['timestamp']
            );
        }
    }

    /**
     * 測試Token最後使用時間更新
     */
    public function test_token_last_used_time_update(): void
    {
        // 取得Token的初始最後使用時間
        $tokenModel = ApiToken::where('token_hash', hash('sha256', $this->validToken))->first();
        $initialLastUsed = $tokenModel->last_used_at;

        // 等待一秒確保時間差異
        sleep(1);

        // 發送API請求
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);

        // 檢查Token的最後使用時間是否已更新
        $tokenModel->refresh();
        $this->assertTrue($tokenModel->last_used_at > $initialLastUsed);
    }

    /**
     * 測試併發請求處理
     */
    public function test_concurrent_request_handling(): void
    {
        $responses = [];
        $requestCount = 5;

        // 模擬併發請求
        for ($i = 1; $i <= $requestCount; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'request_id' => $i
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);
        }

        // 驗證所有請求都成功
        foreach ($responses as $index => $response) {
            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success'
                    ]);
            
            $responseData = $response->json();
            $this->assertEquals($this->user->id, $responseData['data']['user_id']);
        }

        // 驗證所有請求都被記錄
        $logCount = ApiLog::where('user_id', $this->user->id)
                          ->where('action_type', 'system.ping')
                          ->count();
        $this->assertEquals($requestCount, $logCount);
    }

    /**
     * 測試大量資料處理
     */
    public function test_large_data_handling(): void
    {
        $largeData = array_fill(0, 100, [
            'id' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->email(),
            'description' => fake()->text(500)
        ]);

        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'large_data' => $largeData
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        // 驗證大量資料請求也被正確記錄
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'system.ping',
            'status_code' => 200
        ]);
    }

    /**
     * 測試特殊字元和編碼處理
     */
    public function test_special_characters_and_encoding_handling(): void
    {
        $specialData = [
            'chinese' => '這是中文測試資料 🚀',
            'emoji' => '😀😃😄😁😆😅😂🤣',
            'json_string' => '{"nested": "json data"}',
            'html_entities' => '&lt;script&gt;alert("test")&lt;/script&gt;',
            'unicode' => 'Ω≈ç√∫˜µ≤≥÷',
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'special_data' => $specialData
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        // 驗證特殊字元在回應中正確處理
        $responseData = $response->json();
        $this->assertIsArray($responseData['data']);
    }

    /**
     * 測試權限控制機制
     */
    public function test_permission_control_mechanism(): void
    {
        // 這個測試假設某些Action需要管理員權限
        // 實際實作會根據具體的權限系統調整

        // 測試一般使用者存取需要管理員權限的Action
        $response = $this->postJson('/api', [
            'action_type' => 'system.server_status' // 假設這需要管理員權限
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 根據實際權限設定，這可能回傳403或200
        // 這裡假設system.server_status不需要特殊權限
        $response->assertStatus(200);

        // 測試管理員使用者存取
        $response = $this->postJson('/api', [
            'action_type' => 'system.server_status'
        ], [
            'Authorization' => "Bearer {$this->adminToken}"
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試API日誌記錄的完整性
     */
    public function test_api_logging_completeness(): void
    {
        // 清空日誌
        DB::table('api_logs')->truncate();

        // 發送成功請求
        $this->postJson('/api', [
            'action_type' => 'system.ping',
            'test_data' => 'logging_test'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 發送失敗請求
        $this->postJson('/api', [
            'action_type' => 'non.existent'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 驗證日誌記錄
        $logs = ApiLog::orderBy('created_at')->get();
        $this->assertCount(2, $logs);

        // 驗證成功請求日誌
        $successLog = $logs->first();
        $this->assertEquals($this->user->id, $successLog->user_id);
        $this->assertEquals('system.ping', $successLog->action_type);
        $this->assertEquals(200, $successLog->status_code);
        $this->assertGreaterThan(0, $successLog->response_time);
        $this->assertNotEmpty($successLog->request_id);
        $this->assertIsArray($successLog->request_data);
        $this->assertArrayHasKey('action_type', $successLog->request_data);

        // 驗證失敗請求日誌
        $errorLog = $logs->last();
        $this->assertEquals($this->user->id, $errorLog->user_id);
        $this->assertEquals('non.existent', $errorLog->action_type);
        $this->assertEquals(404, $errorLog->status_code);
        $this->assertGreaterThan(0, $errorLog->response_time);
        $this->assertNotEmpty($errorLog->request_id);
    }

    /**
     * 測試系統在高負載下的穩定性
     */
    public function test_system_stability_under_load(): void
    {
        $requestCount = 20;
        $successCount = 0;

        for ($i = 0; $i < $requestCount; $i++) {
            $response = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'load_test_id' => $i
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }

        // 驗證所有請求都成功處理
        $this->assertEquals($requestCount, $successCount);

        // 驗證所有請求都被記錄
        $logCount = ApiLog::where('user_id', $this->user->id)
                          ->where('action_type', 'system.ping')
                          ->count();
        $this->assertEquals($requestCount, $logCount);
    }

    /**
     * 測試錯誤回應中不包含敏感資訊
     */
    public function test_error_responses_do_not_leak_sensitive_information(): void
    {
        // 測試各種錯誤情況
        $errorRequests = [
            // 無效Token
            [
                'request' => ['action_type' => 'system.ping'],
                'headers' => ['Authorization' => 'Bearer invalid_token']
            ],
            // 不存在的Action
            [
                'request' => ['action_type' => 'secret.internal.action'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"]
            ]
        ];

        foreach ($errorRequests as $errorRequest) {
            $response = $this->postJson('/api', $errorRequest['request'], $errorRequest['headers']);
            
            $content = $response->getContent();
            
            // 確保回應中不包含敏感資訊
            $this->assertStringNotContainsString('password', strtolower($content));
            $this->assertStringNotContainsString('secret', strtolower($content));
            $this->assertStringNotContainsString('token_hash', strtolower($content));
            $this->assertStringNotContainsString('database', strtolower($content));
            $this->assertStringNotContainsString('exception', strtolower($content));
            $this->assertStringNotContainsString('stack trace', strtolower($content));
        }
    }
}