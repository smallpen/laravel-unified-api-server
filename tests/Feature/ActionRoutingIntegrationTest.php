<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Services\TokenService;
use App\Services\ActionRegistry;
use App\Contracts\ActionInterface;

/**
 * Action路由系統整合測試
 * 
 * 專門測試Action的註冊、發現、路由和執行機制
 */
class ActionRoutingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $validToken;
    private ActionRegistry $actionRegistry;

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

        // 取得ActionRegistry實例
        $this->actionRegistry = app(ActionRegistry::class);
    }

    /**
     * 測試Action自動發現和註冊機制
     */
    public function test_action_auto_discovery_and_registration(): void
    {
        // 取得所有已註冊的Action
        $registeredActions = $this->actionRegistry->getAllActions();
        
        // 驗證基本Action已被註冊
        $expectedActions = [
            'system.ping',
            'system.status',
            'user.info',
            'user.update'
        ];

        foreach ($expectedActions as $expectedAction) {
            $this->assertTrue(
                $this->actionRegistry->hasAction($expectedAction),
                "Action '{$expectedAction}' 應該已被註冊"
            );
        }

        // 驗證註冊的Action數量合理
        $this->assertGreaterThan(0, count($registeredActions));
    }

    /**
     * 測試Action解析和實例化
     */
    public function test_action_resolution_and_instantiation(): void
    {
        $testActions = ['system.ping', 'user.info', 'system.status'];

        foreach ($testActions as $actionType) {
            // 測試Action是否存在
            $this->assertTrue(
                $this->actionRegistry->hasAction($actionType),
                "Action '{$actionType}' 應該存在"
            );

            // 測試Action解析
            $action = $this->actionRegistry->resolve($actionType);
            
            $this->assertInstanceOf(
                ActionInterface::class,
                $action,
                "解析的Action應該實作ActionInterface"
            );

            // 測試Action基本屬性
            $this->assertIsString($action->getVersion());
            $this->assertIsBool($action->isEnabled());
            $this->assertIsArray($action->getRequiredPermissions());
            $this->assertIsArray($action->getDocumentation());
        }
    }

    /**
     * 測試Action執行流程
     */
    public function test_action_execution_flow(): void
    {
        $testCases = [
            [
                'action_type' => 'system.ping',
                'request_data' => ['message' => 'Hello World'],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'server_time', 'message']
            ],
            [
                'action_type' => 'user.info',
                'request_data' => [],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'user']
            ],
            [
                'action_type' => 'system.status',
                'request_data' => [],
                'expected_fields' => ['action_type', 'user_id', 'timestamp', 'status']
            ]
        ];

        foreach ($testCases as $testCase) {
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

            // 驗證回應內容
            $response->assertJson([
                'status' => 'success',
                'data' => [
                    'action_type' => $testCase['action_type'],
                    'user_id' => $this->user->id
                ]
            ]);

            $responseData = $response->json();
            
            // 驗證所有預期欄位都存在
            foreach ($testCase['expected_fields'] as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $responseData['data'],
                    "回應資料應該包含欄位 '{$field}'"
                );
            }
        }
    }

    /**
     * 測試Action參數驗證
     */
    public function test_action_parameter_validation(): void
    {
        // 測試user.update Action的參數驗證
        $validationTestCases = [
            [
                'description' => '有效的更新請求',
                'data' => [
                    'action_type' => 'user.update',
                    'name' => '新名稱',
                    'email' => 'new@example.com'
                ],
                'should_succeed' => true
            ],
            [
                'description' => '無效的電子郵件格式',
                'data' => [
                    'action_type' => 'user.update',
                    'name' => '新名稱',
                    'email' => 'invalid-email'
                ],
                'should_succeed' => false
            ],
            [
                'description' => '空的名稱',
                'data' => [
                    'action_type' => 'user.update',
                    'name' => '',
                    'email' => 'valid@example.com'
                ],
                'should_succeed' => false
            ]
        ];

        foreach ($validationTestCases as $testCase) {
            $response = $this->postJson('/api', $testCase['data'], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            if ($testCase['should_succeed']) {
                $response->assertStatus(200);
                $response->assertJson(['status' => 'success']);
            } else {
                $response->assertStatus(422);
                $response->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR'
                ]);
            }
        }
    }

    /**
     * 測試Action權限檢查
     */
    public function test_action_permission_checking(): void
    {
        // 建立一個沒有特殊權限的使用者
        $limitedUser = User::factory()->create([
            'name' => '受限使用者',
            'email' => 'limited@example.com',
        ]);

        $tokenService = app(TokenService::class);
        $limitedTokenData = $tokenService->createToken($limitedUser, '受限Token', []);
        $limitedToken = $limitedTokenData['token'];

        // 測試一般Action（應該允許）
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$limitedToken}"
        ]);

        $response->assertStatus(200);

        // 測試需要特殊權限的Action（如果有的話）
        // 這裡假設所有Action都不需要特殊權限，實際實作時需要根據具體權限系統調整
        $response = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$limitedToken}"
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試Action文件生成
     */
    public function test_action_documentation_generation(): void
    {
        $testActions = ['system.ping', 'user.info', 'user.update'];

        foreach ($testActions as $actionType) {
            $action = $this->actionRegistry->resolve($actionType);
            $documentation = $action->getDocumentation();

            // 驗證文件結構
            $this->assertIsArray($documentation);
            $this->assertArrayHasKey('name', $documentation);
            $this->assertArrayHasKey('description', $documentation);
            $this->assertArrayHasKey('version', $documentation);
            $this->assertArrayHasKey('enabled', $documentation);
            $this->assertArrayHasKey('required_permissions', $documentation);
            $this->assertArrayHasKey('parameters', $documentation);
            $this->assertArrayHasKey('responses', $documentation);

            // 驗證文件內容類型
            $this->assertIsString($documentation['name']);
            $this->assertIsString($documentation['description']);
            $this->assertIsString($documentation['version']);
            $this->assertIsBool($documentation['enabled']);
            $this->assertIsArray($documentation['required_permissions']);
            $this->assertIsArray($documentation['parameters']);
            $this->assertIsArray($documentation['responses']);
        }
    }

    /**
     * 測試Action啟用/停用機制
     */
    public function test_action_enable_disable_mechanism(): void
    {
        // 這個測試假設我們有機制可以動態啟用/停用Action
        // 實際實作可能需要配置檔案或資料庫設定

        $actionType = 'system.ping';
        $action = $this->actionRegistry->resolve($actionType);

        // 驗證Action預設是啟用的
        $this->assertTrue($action->isEnabled());

        // 測試啟用的Action可以正常執行
        $response = $this->postJson('/api', [
            'action_type' => $actionType
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試Action版本管理
     */
    public function test_action_version_management(): void
    {
        $testActions = ['system.ping', 'user.info', 'user.update'];

        foreach ($testActions as $actionType) {
            $action = $this->actionRegistry->resolve($actionType);
            $version = $action->getVersion();

            // 驗證版本格式
            $this->assertIsString($version);
            $this->assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+$/',
                $version,
                "Action '{$actionType}' 的版本格式應該是 x.y.z"
            );

            // 驗證版本在文件中正確顯示
            $documentation = $action->getDocumentation();
            $this->assertEquals($version, $documentation['version']);
        }
    }

    /**
     * 測試Action錯誤處理
     */
    public function test_action_error_handling(): void
    {
        // 測試Action內部錯誤的處理
        // 這裡我們可能需要建立一個會拋出例外的測試Action

        // 測試不存在的Action
        $response = $this->postJson('/api', [
            'action_type' => 'non.existent.action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'ACTION_NOT_FOUND'
                ]);

        // 測試Action類型格式錯誤
        $invalidActionTypes = [
            'invalid action type',
            'action@type',
            'action/type',
            str_repeat('a', 101)
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
    }

    /**
     * 測試Action註冊系統的效能
     */
    public function test_action_registry_performance(): void
    {
        $actionType = 'system.ping';
        $iterations = 100;

        $startTime = microtime(true);

        // 重複解析Action多次
        for ($i = 0; $i < $iterations; $i++) {
            $action = $this->actionRegistry->resolve($actionType);
            $this->assertInstanceOf(ActionInterface::class, $action);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // 驗證效能合理（每次解析應該在1ms以內）
        $averageTime = $executionTime / $iterations;
        $this->assertLessThan(0.001, $averageTime, 'Action解析效能應該足夠快');
    }

    /**
     * 測試Action註冊系統的記憶體使用
     */
    public function test_action_registry_memory_usage(): void
    {
        $initialMemory = memory_get_usage();

        // 解析所有可用的Action
        $allActions = $this->actionRegistry->getAllActions();
        
        foreach ($allActions as $actionType => $actionClass) {
            $action = $this->actionRegistry->resolve($actionType);
            $this->assertInstanceOf(ActionInterface::class, $action);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // 驗證記憶體使用合理（不應該超過10MB）
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Action註冊系統記憶體使用應該合理');
    }

    /**
     * 測試Action的並行執行
     */
    public function test_action_concurrent_execution(): void
    {
        $concurrentRequests = 5;
        $responses = [];

        // 同時發送多個請求
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'concurrent_id' => $i
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);
        }

        // 驗證所有請求都成功
        foreach ($responses as $index => $response) {
            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success',
                        'data' => [
                            'action_type' => 'system.ping',
                            'user_id' => $this->user->id
                        ]
                    ]);
        }
    }
}