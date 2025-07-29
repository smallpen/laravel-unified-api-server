<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Services\TokenService;
use App\Services\ResponseFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * 錯誤處理和回應格式整合測試
 * 
 * 專門測試系統的錯誤處理機制和回應格式標準化，包括：
 * - 各種HTTP錯誤狀態碼的處理
 * - 回應格式的一致性
 * - 錯誤訊息的本地化
 * - 敏感資訊的保護
 * - 錯誤日誌記錄
 */
class ErrorHandlingAndResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $validToken;
    private ResponseFormatter $responseFormatter;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);

        // 建立有效的API Token
        $tokenService = app(TokenService::class);
        $tokenData = $tokenService->createToken($this->user, '測試Token');
        $this->validToken = $tokenData['token'];

        // 取得ResponseFormatter實例
        $this->responseFormatter = app(ResponseFormatter::class);
    }

    /**
     * 測試成功回應格式的標準化
     */
    public function test_success_response_format_standardization(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'message' => '成功測試'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);

        // 驗證成功回應的標準結構
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'action_type',
                'user_id',
                'timestamp'
            ],
            'timestamp'
        ]);

        $responseData = $response->json();

        // 驗證成功回應的標準欄位
        $this->assertEquals('success', $responseData['status']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
        $this->assertIsString($responseData['timestamp']);

        // 驗證時間戳格式
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['timestamp']
        );

        // 驗證資料欄位包含必要資訊
        $this->assertArrayHasKey('action_type', $responseData['data']);
        $this->assertArrayHasKey('user_id', $responseData['data']);
        $this->assertArrayHasKey('timestamp', $responseData['data']);
    }

    /**
     * 測試401未授權錯誤處理
     */
    public function test_401_unauthorized_error_handling(): void
    {
        $testCases = [
            [
                'description' => '缺少Authorization標頭',
                'headers' => [],
                'expected_message_contains' => '未提供'
            ],
            [
                'description' => '無效的Bearer Token',
                'headers' => ['Authorization' => 'Bearer invalid_token_12345'],
                'expected_message_contains' => '無效'
            ],
            [
                'description' => '錯誤的Authorization格式',
                'headers' => ['Authorization' => 'Basic dGVzdDp0ZXN0'],
                'expected_message_contains' => '格式'
            ]
        ];

        foreach ($testCases as $testCase) {
            $response = $this->postJson('/api', [
                'action_type' => 'system.ping'
            ], $testCase['headers']);

            $response->assertStatus(401);

            // 驗證錯誤回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp'
            ]);

            $responseData = $response->json();

            // 驗證錯誤回應內容
            $this->assertEquals('error', $responseData['status']);
            $this->assertEquals('UNAUTHORIZED', $responseData['error_code']);
            $this->assertIsString($responseData['message']);
            $this->assertIsString($responseData['timestamp']);

            // 驗證時間戳格式
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $responseData['timestamp']
            );
        }
    }

    /**
     * 測試404未找到錯誤處理
     */
    public function test_404_not_found_error_handling(): void
    {
        $testCases = [
            [
                'description' => '不存在的Action',
                'request_data' => ['action_type' => 'non.existent.action'],
                'expected_error_code' => 'ACTION_NOT_FOUND'
            ],
            [
                'description' => '不存在的路由',
                'url' => '/api/non-existent-route',
                'expected_error_code' => 'NOT_FOUND'
            ]
        ];

        foreach ($testCases as $testCase) {
            if (isset($testCase['url'])) {
                $response = $this->postJson($testCase['url'], [], [
                    'Authorization' => "Bearer {$this->validToken}"
                ]);
            } else {
                $response = $this->postJson('/api', $testCase['request_data'], [
                    'Authorization' => "Bearer {$this->validToken}"
                ]);
            }

            $response->assertStatus(404);

            // 驗證錯誤回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp'
            ]);

            $responseData = $response->json();

            // 驗證錯誤回應內容
            $this->assertEquals('error', $responseData['status']);
            $this->assertEquals($testCase['expected_error_code'], $responseData['error_code']);
            $this->assertIsString($responseData['message']);
            $this->assertIsString($responseData['timestamp']);
        }
    }

    /**
     * 測試405方法不允許錯誤處理
     */
    public function test_405_method_not_allowed_error_handling(): void
    {
        $methods = ['GET', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $response = $this->json($method, '/api', [
                'action_type' => 'system.ping'
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(405);

            // 驗證錯誤回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp'
            ]);

            $responseData = $response->json();

            // 驗證錯誤回應內容
            $this->assertEquals('error', $responseData['status']);
            $this->assertEquals('METHOD_NOT_ALLOWED', $responseData['error_code']);
            $this->assertStringContainsString($method, $responseData['message']);
        }
    }

    /**
     * 測試422驗證錯誤處理
     */
    public function test_422_validation_error_handling(): void
    {
        $validationTestCases = [
            [
                'description' => '缺少action_type參數',
                'request_data' => ['data' => 'test'],
                'expected_field_errors' => ['action_type']
            ],
            [
                'description' => 'action_type格式錯誤',
                'request_data' => ['action_type' => 'invalid action type'],
                'expected_field_errors' => ['action_type']
            ],
            [
                'description' => 'action_type過長',
                'request_data' => ['action_type' => str_repeat('a', 101)],
                'expected_field_errors' => ['action_type']
            ],
            [
                'description' => 'action_type包含非法字元',
                'request_data' => ['action_type' => 'action@type'],
                'expected_field_errors' => ['action_type']
            ]
        ];

        foreach ($validationTestCases as $testCase) {
            $response = $this->postJson('/api', $testCase['request_data'], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(422);

            // 驗證錯誤回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'details',
                'timestamp'
            ]);

            $responseData = $response->json();

            // 驗證錯誤回應內容
            $this->assertEquals('error', $responseData['status']);
            $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
            $this->assertIsArray($responseData['details']);

            // 驗證具體的欄位錯誤
            foreach ($testCase['expected_field_errors'] as $field) {
                $this->assertArrayHasKey($field, $responseData['details']);
                $this->assertIsArray($responseData['details'][$field]);
                $this->assertNotEmpty($responseData['details'][$field]);
            }
        }
    }

    /**
     * 測試500內部伺服器錯誤處理
     */
    public function test_500_internal_server_error_handling(): void
    {
        // 建立會拋出例外的測試路由
        Route::post('/api/test/internal-error', function () {
            throw new \Exception('測試內部錯誤');
        });

        // 測試開發環境的錯誤處理
        $this->app['env'] = 'local';
        
        $response = $this->postJson('/api/test/internal-error', [], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(500);

        // 驗證錯誤回應結構
        $response->assertJsonStructure([
            'status',
            'message',
            'error_code',
            'timestamp'
        ]);

        $responseData = $response->json();

        // 驗證錯誤回應內容
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $responseData['error_code']);
        $this->assertIsString($responseData['message']);

        // 測試生產環境的錯誤處理
        $this->app['env'] = 'production';
        
        $response = $this->postJson('/api/test/internal-error', [], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(500);

        $responseData = $response->json();

        // 在生產環境中，錯誤訊息應該是通用的
        $this->assertEquals('系統發生內部錯誤，請稍後再試', $responseData['message']);
        
        // 確保不包含敏感的除錯資訊
        $content = $response->getContent();
        $this->assertStringNotContainsString('測試內部錯誤', $content);
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('stack trace', $content);
    }

    /**
     * 測試回應格式一致性
     */
    public function test_response_format_consistency(): void
    {
        $testScenarios = [
            [
                'description' => '成功回應',
                'request' => ['action_type' => 'system.ping'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 200,
                'expected_structure' => ['status', 'message', 'data', 'timestamp']
            ],
            [
                'description' => '未授權錯誤',
                'request' => ['action_type' => 'system.ping'],
                'headers' => [],
                'expected_status' => 401,
                'expected_structure' => ['status', 'message', 'error_code', 'timestamp']
            ],
            [
                'description' => '未找到錯誤',
                'request' => ['action_type' => 'non.existent'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 404,
                'expected_structure' => ['status', 'message', 'error_code', 'timestamp']
            ],
            [
                'description' => '驗證錯誤',
                'request' => ['invalid_param' => 'test'],
                'headers' => ['Authorization' => "Bearer {$this->validToken}"],
                'expected_status' => 422,
                'expected_structure' => ['status', 'message', 'error_code', 'details', 'timestamp']
            ]
        ];

        foreach ($testScenarios as $scenario) {
            $response = $this->postJson('/api', $scenario['request'], $scenario['headers']);

            $response->assertStatus($scenario['expected_status']);
            $response->assertJsonStructure($scenario['expected_structure']);

            $responseData = $response->json();

            // 驗證所有回應都有基本欄位
            $this->assertArrayHasKey('status', $responseData);
            $this->assertArrayHasKey('message', $responseData);
            $this->assertArrayHasKey('timestamp', $responseData);

            // 驗證狀態欄位值
            if ($scenario['expected_status'] === 200) {
                $this->assertEquals('success', $responseData['status']);
            } else {
                $this->assertEquals('error', $responseData['status']);
                $this->assertArrayHasKey('error_code', $responseData);
            }

            // 驗證時間戳格式
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $responseData['timestamp']
            );
        }
    }

    /**
     * 測試錯誤訊息本地化
     */
    public function test_error_message_localization(): void
    {
        // 測試中文錯誤訊息
        $response = $this->postJson('/api', [
            'action_type' => 'non.existent.action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(404);
        $responseData = $response->json();

        // 驗證錯誤訊息是中文
        $this->assertStringContainsString('找不到', $responseData['message']);

        // 測試驗證錯誤的中文訊息
        $response = $this->postJson('/api', [
            'action_type' => ''
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422);
        $responseData = $response->json();

        // 驗證驗證錯誤訊息是中文
        $this->assertIsArray($responseData['details']);
        $this->assertArrayHasKey('action_type', $responseData['details']);
    }

    /**
     * 測試敏感資訊保護
     */
    public function test_sensitive_information_protection(): void
    {
        // 建立會洩漏敏感資訊的測試路由
        Route::post('/api/test/sensitive-error', function () {
            throw new \PDOException('SQLSTATE[42S02]: Base table or view not found: 1146 Table \'secret_database.users\' doesn\'t exist');
        });

        // 測試生產環境下敏感資訊不會洩漏
        $this->app['env'] = 'production';
        
        $response = $this->postJson('/api/test/sensitive-error', [], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(500);
        $content = $response->getContent();

        // 確保敏感資訊不會出現在回應中
        $sensitiveTerms = [
            'secret_database',
            'SQLSTATE',
            'PDOException',
            'password',
            'token_hash',
            'private_key',
            'secret_key'
        ];

        foreach ($sensitiveTerms as $term) {
            $this->assertStringNotContainsString(
                $term,
                strtolower($content),
                "回應中不應包含敏感資訊: {$term}"
            );
        }

        // 驗證回應只包含通用錯誤訊息
        $responseData = $response->json();
        $this->assertEquals('系統發生內部錯誤，請稍後再試', $responseData['message']);
    }

    /**
     * 測試錯誤日誌記錄
     */
    public function test_error_logging(): void
    {
        // 清空日誌
        DB::table('api_logs')->truncate();

        // 發送會產生錯誤的請求
        $this->postJson('/api', [
            'action_type' => 'non.existent.action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        // 驗證錯誤被記錄到api_logs表
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'non.existent.action',
            'status_code' => 404
        ]);

        $log = DB::table('api_logs')->where('user_id', $this->user->id)->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertNotEmpty($log->request_id);
    }

    /**
     * 測試請求ID追蹤
     */
    public function test_request_id_tracking(): void
    {
        $responses = [];
        
        // 發送多個請求
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'request_number' => $i
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);
        }

        $requestIds = [];
        
        foreach ($responses as $response) {
            $response->assertStatus(200);
            
            // 檢查回應中是否包含請求ID（如果有的話）
            // 或者檢查日誌中的請求ID
            $responseData = $response->json();
            
            // 從日誌中取得請求ID
            $log = DB::table('api_logs')
                    ->where('user_id', $this->user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            
            if ($log && $log->request_id) {
                $requestIds[] = $log->request_id;
            }
        }

        // 驗證每個請求都有唯一的請求ID
        if (!empty($requestIds)) {
            $this->assertEquals(count($requestIds), count(array_unique($requestIds)));
            
            // 驗證請求ID格式（UUID）
            foreach ($requestIds as $requestId) {
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
                    $requestId
                );
            }
        }
    }

    /**
     * 測試大量錯誤請求的處理
     */
    public function test_bulk_error_request_handling(): void
    {
        $errorRequests = 10;
        $successCount = 0;

        for ($i = 0; $i < $errorRequests; $i++) {
            $response = $this->postJson('/api', [
                'action_type' => "non.existent.action.{$i}"
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            if ($response->getStatusCode() === 404) {
                $successCount++;
            }

            // 驗證每個錯誤回應都有正確的格式
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp'
            ]);
        }

        // 驗證所有錯誤請求都被正確處理
        $this->assertEquals($errorRequests, $successCount);

        // 驗證所有錯誤都被記錄
        $logCount = DB::table('api_logs')
                     ->where('user_id', $this->user->id)
                     ->where('status_code', 404)
                     ->count();
        $this->assertEquals($errorRequests, $logCount);
    }

    /**
     * 測試回應時間記錄
     */
    public function test_response_time_recording(): void
    {
        // 清空日誌
        DB::table('api_logs')->truncate();

        // 發送請求
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);

        // 檢查日誌中的回應時間
        $log = DB::table('api_logs')->where('user_id', $this->user->id)->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertLessThan(10, $log->response_time); // 回應時間應該在10秒以內
    }

    /**
     * 測試特殊字元在錯誤訊息中的處理
     */
    public function test_special_characters_in_error_messages(): void
    {
        $specialActionTypes = [
            'action<script>alert("xss")</script>',
            'action"with"quotes',
            'action\'with\'single\'quotes',
            'action&with&entities',
            'action\nwith\nnewlines'
        ];

        foreach ($specialActionTypes as $actionType) {
            $response = $this->postJson('/api', [
                'action_type' => $actionType
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            // 應該是驗證錯誤或未找到錯誤
            $this->assertContains($response->getStatusCode(), [404, 422]);

            $content = $response->getContent();
            
            // 確保特殊字元被正確轉義，不會造成XSS
            $this->assertStringNotContainsString('<script>', $content);
            $this->assertStringNotContainsString('alert(', $content);
        }
    }
}