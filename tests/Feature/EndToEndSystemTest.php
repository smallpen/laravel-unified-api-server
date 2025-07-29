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
use App\Services\DocumentationGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

/**
 * 端到端系統測試
 * 
 * 這個測試類別在Docker環境中執行完整系統測試，驗證：
 * - 所有API功能正常運作
 * - Bearer Token驗證流程
 * - Action路由和執行
 * - 文件生成和Swagger UI
 * - 日誌和監控功能
 * - 錯誤處理機制
 * - 系統整體穩定性
 * 
 * 對應需求：1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 2.4, 4.2, 4.4, 5.1, 5.2, 5.3, 5.4, 7.2, 7.4
 */
class EndToEndSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private User $adminUser;
    private string $userToken;
    private string $adminToken;
    private string $expiredToken;
    private TokenService $tokenService;
    private ActionRegistry $actionRegistry;
    private ResponseFormatter $responseFormatter;
    private DocumentationGenerator $documentationGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 初始化服務
        $this->tokenService = app(TokenService::class);
        $this->actionRegistry = app(ActionRegistry::class);
        $this->responseFormatter = app(ResponseFormatter::class);
        $this->documentationGenerator = app(DocumentationGenerator::class);

        // 建立測試使用者和Token
        $this->setupTestUsers();
        $this->setupTestTokens();
        
        // 清空測試資料
        $this->cleanupTestData();
    }

    /**
     * 設定測試使用者
     */
    private function setupTestUsers(): void
    {
        $this->testUser = User::factory()->create([
            'name' => '端到端測試使用者',
            'email' => 'e2e_user_' . uniqid() . '@example.com',
        ]);

        $this->adminUser = User::factory()->create([
            'name' => '端到端管理員使用者',
            'email' => 'e2e_admin_' . uniqid() . '@example.com',
        ]);
    }

    /**
     * 設定測試Token
     */
    private function setupTestTokens(): void
    {
        // 建立一般使用者Token（包含基本權限）
        $userTokenData = $this->tokenService->createToken(
            $this->testUser, 
            '端到端測試Token',
            ['system.server_status', 'user.read', 'user.update'] // 添加測試所需權限
        );
        $this->userToken = $userTokenData['token'];

        // 建立管理員Token
        $adminTokenData = $this->tokenService->createToken(
            $this->adminUser, 
            '端到端管理員Token', 
            ['admin', 'user.manage', 'system.monitor']
        );
        $this->adminToken = $adminTokenData['token'];

        // 建立過期Token
        $expiredTokenData = $this->tokenService->createToken(
            $this->testUser, 
            '端到端過期Token', 
            [], 
            now()->subDay()
        );
        $this->expiredToken = $expiredTokenData['token'];
    }

    /**
     * 清理測試資料
     */
    private function cleanupTestData(): void
    {
        DB::table('api_logs')->truncate();
        Cache::flush();
    }

    /**
     * 測試完整的API功能運作
     * 
     * 需求：1.1, 1.2, 1.3 - 統一接口路徑處理所有API請求
     */
    public function test_complete_api_functionality(): void
    {
        $this->markTestAsSystemTest('完整API功能測試');

        // 測試所有核心Action
        $coreActions = [
            'system.ping' => [
                'request' => ['message' => '端到端測試'],
                'expected_fields' => ['user_id', 'message', 'system_status', 'timestamp', 'server_time']
            ],
            'system.server_status' => [
                'request' => [],
                'expected_fields' => ['server_status']
            ],
            'user.info' => [
                'request' => [],
                'expected_fields' => ['user']
            ],
            'user.update' => [
                'request' => [
                    'name' => '端到端更新測試',
                    'email' => 'e2e_updated_' . uniqid() . '@example.com'
                ],
                'expected_fields' => ['user', 'message']
            ]
        ];

        foreach ($coreActions as $actionType => $testData) {
            $requestData = array_merge(['action_type' => $actionType], $testData['request']);
            
            $response = $this->postJson('/api', $requestData, [
                'Authorization' => "Bearer {$this->userToken}",
                'User-Agent' => 'EndToEndSystemTest/1.0'
            ]);



            // 驗證回應狀態和結構
            $response->assertStatus(200)
                    ->assertJsonStructure([
                        'status',
                        'message',
                        'data' => $testData['expected_fields'],
                        'timestamp'
                    ])
                    ->assertJson([
                        'status' => 'success'
                    ]);

            // 對於包含user_id的Action，額外驗證user_id
            if (in_array('user_id', $testData['expected_fields'])) {
                $response->assertJson([
                    'data' => [
                        'user_id' => $this->testUser->id
                    ]
                ]);
            }

            // 驗證日誌記錄
            $this->assertDatabaseHas('api_logs', [
                'user_id' => $this->testUser->id,
                'action_type' => $actionType,
                'status_code' => 200
            ]);
        }

        $this->addToReport('完整API功能測試', '所有核心Action都正常運作');
    }

    /**
     * 測試Bearer Token驗證流程
     * 
     * 需求：2.1, 2.2, 2.3, 2.4 - Bearer Token身份驗證
     */
    public function test_bearer_token_authentication_flow(): void
    {
        $this->markTestAsSystemTest('Bearer Token驗證流程測試');

        $authTestCases = [
            [
                'name' => '有效Token驗證',
                'token' => $this->userToken,
                'expected_status' => 200,
                'expected_user_id' => $this->testUser->id
            ],
            [
                'name' => '管理員Token驗證',
                'token' => $this->adminToken,
                'expected_status' => 200,
                'expected_user_id' => $this->adminUser->id
            ],
            [
                'name' => '過期Token拒絕',
                'token' => $this->expiredToken,
                'expected_status' => 401,
                'expected_error' => 'UNAUTHORIZED'
            ],
            [
                'name' => '無效Token拒絕',
                'token' => 'invalid_token_' . uniqid(),
                'expected_status' => 401,
                'expected_error' => 'UNAUTHORIZED'
            ]
        ];

        foreach ($authTestCases as $testCase) {
            $response = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'test_case' => $testCase['name']
            ], [
                'Authorization' => "Bearer {$testCase['token']}"
            ]);

            $response->assertStatus($testCase['expected_status']);

            if ($testCase['expected_status'] === 200) {
                $response->assertJson([
                    'status' => 'success',
                    'data' => [
                        'user_id' => $testCase['expected_user_id']
                    ]
                ]);
            } else {
                $response->assertJson([
                    'status' => 'error',
                    'error_code' => $testCase['expected_error']
                ]);
            }
        }

        // 驗證Token使用記錄更新
        $tokenModel = ApiToken::where('token_hash', hash('sha256', $this->userToken))->first();
        $this->assertNotNull($tokenModel->last_used_at);
        $this->assertTrue($tokenModel->last_used_at->isAfter(now()->subMinute()));

        $this->addToReport('Bearer Token驗證流程測試', '所有Token驗證場景都正確處理');
    }

    /**
     * 測試文件生成和Swagger UI
     * 
     * 需求：4.2, 4.4 - API文件自動生成和Swagger UI
     */
    public function test_documentation_generation_and_swagger_ui(): void
    {
        $this->markTestAsSystemTest('文件生成和Swagger UI測試');

        // 測試API文件生成
        $response = $this->get('/api/docs');
        $response->assertStatus(200);
        $response->assertViewIs('documentation.swagger-ui');

        // 測試Swagger UI頁面（已經在上面測試過了，這裡檢查內容）
        $response->assertSee('Swagger UI');
        $response->assertSee('統一API伺服器');

        // 測試OpenAPI JSON格式文件
        $response = $this->get('/api/docs/openapi.json');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $openApiData = $response->json();
        $this->assertArrayHasKey('openapi', $openApiData);
        $this->assertArrayHasKey('info', $openApiData);
        $this->assertArrayHasKey('paths', $openApiData);
        $this->assertArrayHasKey('components', $openApiData);

        // 驗證文件包含所有Action
        $this->assertArrayHasKey('/', $openApiData['paths']);
        $this->assertArrayHasKey('post', $openApiData['paths']['/']);

        // 測試文件生成服務
        $documentation = $this->documentationGenerator->generateDocumentation();
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('actions', $documentation);
        $this->assertGreaterThan(0, count($documentation['actions']));

        // 驗證每個Action都有文件
        $availableActions = $this->actionRegistry->getAllActions();
        foreach ($availableActions as $actionType => $actionClass) {
            $this->assertArrayHasKey($actionType, $documentation['actions']);
            $actionDoc = $documentation['actions'][$actionType];
            $this->assertArrayHasKey('name', $actionDoc);
            $this->assertArrayHasKey('description', $actionDoc);
            $this->assertArrayHasKey('parameters', $actionDoc);
        }

        $this->addToReport('文件生成和Swagger UI測試', '文件生成功能和Swagger UI都正常運作');
    }

    /**
     * 測試日誌和監控功能
     * 
     * 需求：7.2, 7.4 - 日誌記錄和監控
     */
    public function test_logging_and_monitoring_functionality(): void
    {
        $this->markTestAsSystemTest('日誌和監控功能測試');

        // 清空日誌
        DB::table('api_logs')->truncate();

        // 發送多種類型的請求
        $testRequests = [
            [
                'type' => '成功請求',
                'data' => ['action_type' => 'system.ping', 'message' => '日誌測試'],
                'token' => $this->userToken,
                'expected_status' => 200
            ],
            [
                'type' => '失敗請求',
                'data' => ['action_type' => 'non.existent.action'],
                'token' => $this->userToken,
                'expected_status' => 404
            ],
            [
                'type' => '驗證錯誤',
                'data' => ['invalid_param' => 'test'],
                'token' => $this->userToken,
                'expected_status' => 422
            ],
            [
                'type' => '認證錯誤',
                'data' => ['action_type' => 'system.ping'],
                'token' => 'invalid_token',
                'expected_status' => 401
            ]
        ];

        $requestTimes = [];
        foreach ($testRequests as $testRequest) {
            $startTime = microtime(true);

            $response = $this->postJson('/api', $testRequest['data'], [
                'Authorization' => "Bearer {$testRequest['token']}",
                'User-Agent' => 'LoggingTest/1.0'
            ]);

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            $requestTimes[] = $responseTime;

            $response->assertStatus($testRequest['expected_status']);
        }

        // 驗證日誌記錄完整性
        $logs = ApiLog::orderBy('created_at')->get();
        $this->assertCount(4, $logs);

        foreach ($logs as $index => $log) {
            $testRequest = $testRequests[$index];
            
            // 驗證基本日誌欄位
            $this->assertEquals($testRequest['expected_status'], $log->status_code);
            $this->assertGreaterThan(0, $log->response_time);
            $this->assertLessThan($requestTimes[$index] + 100, $log->response_time); // 允許100ms誤差
            $this->assertNotEmpty($log->request_id);
            $this->assertIsArray($log->request_data);
            $this->assertNotEmpty($log->ip_address);
            $this->assertNotEmpty($log->user_agent);
            $this->assertEquals('LoggingTest/1.0', $log->user_agent);

            // 驗證使用者ID記錄
            if ($testRequest['token'] === $this->userToken) {
                $this->assertEquals($this->testUser->id, $log->user_id);
            } elseif ($testRequest['expected_status'] === 401) {
                $this->assertNull($log->user_id);
            }

            // 驗證請求資料記錄
            foreach ($testRequest['data'] as $key => $value) {
                $this->assertEquals($value, $log->request_data[$key]);
            }
        }

        // 測試日誌查詢和統計功能
        $successLogs = ApiLog::where('status_code', 200)->count();
        $errorLogs = ApiLog::where('status_code', '>=', 400)->count();
        $this->assertEquals(1, $successLogs);
        $this->assertEquals(3, $errorLogs);

        // 測試平均回應時間計算
        $averageResponseTime = ApiLog::avg('response_time');
        $this->assertGreaterThan(0, $averageResponseTime);
        $this->assertLessThan(5000, $averageResponseTime); // 應該在5秒內

        $this->addToReport('日誌和監控功能測試', '所有請求都被正確記錄，統計功能正常');
    }

    /**
     * 測試錯誤處理機制
     * 
     * 需求：5.3, 5.4 - 錯誤處理和回應格式
     */
    public function test_error_handling_mechanisms(): void
    {
        $this->markTestAsSystemTest('錯誤處理機制測試');

        $errorScenarios = [
            [
                'name' => 'HTTP方法錯誤',
                'method' => 'GET',
                'data' => ['action_type' => 'system.ping'],
                'headers' => ['Authorization' => "Bearer {$this->userToken}"],
                'expected_status' => 405,
                'expected_error_code' => 'METHOD_NOT_ALLOWED'
            ],
            [
                'name' => '缺少認證標頭',
                'method' => 'POST',
                'data' => ['action_type' => 'system.ping'],
                'headers' => [],
                'expected_status' => 401,
                'expected_error_code' => 'UNAUTHORIZED'
            ],
            [
                'name' => '無效的action_type',
                'method' => 'POST',
                'data' => ['action_type' => 'invalid.action'],
                'headers' => ['Authorization' => "Bearer {$this->userToken}"],
                'expected_status' => 404,
                'expected_error_code' => 'ACTION_NOT_FOUND'
            ],
            [
                'name' => '缺少action_type參數',
                'method' => 'POST',
                'data' => ['message' => 'test'],
                'headers' => ['Authorization' => "Bearer {$this->userToken}"],
                'expected_status' => 422,
                'expected_error_code' => 'VALIDATION_ERROR'
            ],
            [
                'name' => 'action_type格式錯誤',
                'method' => 'POST',
                'data' => ['action_type' => 'invalid format'],
                'headers' => ['Authorization' => "Bearer {$this->userToken}"],
                'expected_status' => 422,
                'expected_error_code' => 'VALIDATION_ERROR'
            ]
        ];

        foreach ($errorScenarios as $scenario) {
            $response = $this->json(
                $scenario['method'],
                '/api',
                $scenario['data'],
                $scenario['headers']
            );

            // 驗證HTTP狀態碼
            $response->assertStatus($scenario['expected_status']);

            // 驗證錯誤回應結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp'
            ]);

            // 驗證錯誤回應內容
            $response->assertJson([
                'status' => 'error',
                'error_code' => $scenario['expected_error_code']
            ]);

            $responseData = $response->json();

            // 驗證錯誤訊息是中文且有意義
            $this->assertNotEmpty($responseData['message']);
            $this->assertIsString($responseData['message']);

            // 驗證時間戳格式
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $responseData['timestamp']
            );

            // 驗證敏感資訊不會洩漏
            $content = $response->getContent();
            $sensitiveTerms = ['password', 'token_hash', 'secret', 'database', 'exception'];
            foreach ($sensitiveTerms as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    strtolower($content),
                    "錯誤回應不應包含敏感資訊: {$term} (場景: {$scenario['name']})"
                );
            }
        }

        $this->addToReport('錯誤處理機制測試', '所有錯誤場景都被正確處理，回應格式一致');
    }

    /**
     * 測試系統整體穩定性
     * 
     * 需求：5.1, 5.2 - 系統穩定性和效能
     */
    public function test_system_overall_stability(): void
    {
        $this->markTestAsSystemTest('系統整體穩定性測試');

        $stabilityTests = [
            'concurrent_requests' => 20,
            'large_data_size' => 500,
            'special_characters' => true,
            'high_frequency' => 30
        ];

        // 測試1: 併發請求處理
        $concurrentResponses = [];
        $startTime = microtime(true);

        for ($i = 0; $i < $stabilityTests['concurrent_requests']; $i++) {
            $concurrentResponses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'concurrent_id' => $i,
                'timestamp' => now()->toISOString()
            ], [
                'Authorization' => "Bearer {$this->userToken}"
            ]);
        }

        $concurrentEndTime = microtime(true);
        $concurrentTotalTime = ($concurrentEndTime - $startTime) * 1000;

        $concurrentSuccessCount = 0;
        foreach ($concurrentResponses as $response) {
            if ($response->getStatusCode() === 200) {
                $concurrentSuccessCount++;
            }
        }

        $this->assertEquals(
            $stabilityTests['concurrent_requests'], 
            $concurrentSuccessCount, 
            '所有併發請求都應該成功'
        );

        // 測試2: 大量資料處理
        $largeData = array_fill(0, $stabilityTests['large_data_size'], [
            'id' => fake()->uuid(),
            'name' => fake()->name(),
            'description' => fake()->text(200)
        ]);

        $largeDataResponse = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'large_data' => $largeData,
            'message' => '大量資料穩定性測試'
        ], [
            'Authorization' => "Bearer {$this->userToken}"
        ]);

        $largeDataResponse->assertStatus(200);

        // 測試3: 特殊字元處理
        if ($stabilityTests['special_characters']) {
            $specialCharsResponse = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'chinese' => '這是中文測試 🚀',
                'emoji' => '😀😃😄😁',
                'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
                'unicode' => 'Ω≈ç√∫˜µ≤≥÷'
            ], [
                'Authorization' => "Bearer {$this->userToken}"
            ]);

            $specialCharsResponse->assertStatus(200);
        }

        // 測試4: 高頻率請求
        $highFrequencySuccessCount = 0;
        $highFrequencyResponseTimes = [];

        for ($i = 0; $i < $stabilityTests['high_frequency']; $i++) {
            $requestStart = microtime(true);

            $response = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'frequency_test_id' => $i
            ], [
                'Authorization' => "Bearer {$this->userToken}"
            ]);

            $requestEnd = microtime(true);
            $responseTime = ($requestEnd - $requestStart) * 1000;
            $highFrequencyResponseTimes[] = $responseTime;

            if ($response->getStatusCode() === 200) {
                $highFrequencySuccessCount++;
            }
        }

        // 驗證穩定性指標
        $highFrequencySuccessRate = ($highFrequencySuccessCount / $stabilityTests['high_frequency']) * 100;
        $this->assertGreaterThanOrEqual(95, $highFrequencySuccessRate, '高頻率請求成功率應該至少95%');

        $averageResponseTime = array_sum($highFrequencyResponseTimes) / count($highFrequencyResponseTimes);
        $this->assertLessThan(2000, $averageResponseTime, '平均回應時間應該在2秒內');

        $maxResponseTime = max($highFrequencyResponseTimes);
        $this->assertLessThan(5000, $maxResponseTime, '最大回應時間應該在5秒內');

        // 驗證所有測試請求都被記錄
        $totalExpectedLogs = $stabilityTests['concurrent_requests'] + 1 + 1 + $stabilityTests['high_frequency'];
        $actualLogCount = ApiLog::where('user_id', $this->testUser->id)->count();
        $this->assertEquals($totalExpectedLogs, $actualLogCount, '所有穩定性測試請求都應該被記錄');

        $this->addToReport('系統整體穩定性測試', "併發處理: {$concurrentSuccessCount}/{$stabilityTests['concurrent_requests']}, 高頻率成功率: {$highFrequencySuccessRate}%, 平均回應時間: " . round($averageResponseTime, 2) . "ms");
    }

    /**
     * 測試Docker環境配置
     * 
     * 需求：3.1, 3.2, 3.3 - Docker容器化部署
     */
    public function test_docker_environment_configuration(): void
    {
        $this->markTestAsSystemTest('Docker環境配置測試');

        // 測試環境變數配置
        $requiredEnvVars = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_DATABASE',
            'CACHE_DRIVER',
            'SESSION_DRIVER'
        ];

        foreach ($requiredEnvVars as $envVar) {
            $this->assertNotEmpty(
                env($envVar), 
                "環境變數 {$envVar} 應該被正確設定"
            );
        }

        // 測試資料庫連線
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
            $dbConnected = false;
        }
        $this->assertTrue($dbConnected, '資料庫連線應該正常');

        // 測試快取連線
        try {
            Cache::put('docker_test_key', 'docker_test_value', 60);
            $cacheValue = Cache::get('docker_test_key');
            $cacheConnected = ($cacheValue === 'docker_test_value');
        } catch (\Exception $e) {
            $cacheConnected = false;
        }
        $this->assertTrue($cacheConnected, '快取連線應該正常');

        // 測試日誌寫入
        try {
            Log::info('Docker環境測試日誌', ['test_id' => 'docker_config_test']);
            $logWritable = true;
        } catch (\Exception $e) {
            $logWritable = false;
        }
        $this->assertTrue($logWritable, '日誌寫入應該正常');

        // 測試Artisan指令執行
        try {
            Artisan::call('route:list');
            $artisanWorking = true;
        } catch (\Exception $e) {
            $artisanWorking = false;
        }
        $this->assertTrue($artisanWorking, 'Artisan指令應該能正常執行');

        $this->addToReport('Docker環境配置測試', '所有環境配置都正確，服務連線正常');
    }

    /**
     * 測試健康檢查端點
     */
    public function test_health_check_endpoints(): void
    {
        $this->markTestAsSystemTest('健康檢查端點測試');

        // 測試基本健康檢查
        $response = $this->get('/health');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy'
        ]);
        
        // 驗證時間戳格式
        $responseData = $response->json();
        $this->assertArrayHasKey('timestamp', $responseData);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $responseData['timestamp']
        );

        // 測試詳細健康檢查
        $response = $this->get('/health/detailed');
        

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
                'cache',
                'storage'
            ],
            'system' => [
                'memory_usage',
                'disk_usage'
            ]
        ]);

        $healthData = $response->json();
        $this->assertEquals('healthy', $healthData['status']);
        $this->assertEquals('connected', $healthData['services']['database']);
        $this->assertEquals('connected', $healthData['services']['cache']);

        $this->addToReport('健康檢查端點測試', '所有健康檢查端點都正常回應');
    }

    /**
     * 標記測試為系統測試
     */
    private function markTestAsSystemTest(string $testName): void
    {
        Log::info("開始執行端到端系統測試: {$testName}", [
            'test_class' => self::class,
            'test_method' => debug_backtrace()[1]['function'],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 添加測試報告
     */
    private function addToReport(string $testName, string $result): void
    {
        Log::info("端到端系統測試完成: {$testName}", [
            'result' => $result,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 測試清理
     */
    protected function tearDown(): void
    {
        // 清理測試資料
        Cache::forget('docker_test_key');
        
        parent::tearDown();
    }
}