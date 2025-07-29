<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * 統一API功能測試
 */
class UnifiedApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->testUser = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);

        // 建立測試Token
        $tokenString = 'test-token-' . uniqid();
        $this->validToken = $tokenString;
        
        ApiToken::create([
            'user_id' => $this->testUser->id,
            'token_hash' => hash('sha256', $tokenString),
            'name' => '測試Token',
            'expires_at' => now()->addDays(30),
            'permissions' => ['*'],
        ]);
    }

    /**
     * 測試成功的API請求
     */
    public function test_successful_api_request(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
            'data' => ['message' => 'hello world'],
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'action_type',
                    'user_id',
                    'timestamp',
                ],
                'timestamp',
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'action_type' => 'test.ping',
                    'user_id' => $this->testUser->id,
                ],
            ]);
    }

    /**
     * 測試缺少Bearer Token的請求
     */
    public function test_request_without_bearer_token(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp',
            ]);
    }

    /**
     * 測試無效的Bearer Token
     */
    public function test_request_with_invalid_bearer_token(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * 測試格式錯誤的Authorization標頭
     */
    public function test_request_with_malformed_authorization_header(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
        ], [
            'Authorization' => 'Basic ' . base64_encode('user:pass'),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * 測試非POST請求方法
     */
    public function test_non_post_request_method(): void
    {
        $response = $this->getJson('/api?action_type=test.ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        // Laravel會在路由層面就攔截非POST請求，回傳405錯誤
        $response->assertStatus(405);
        
        // 檢查回應包含方法不允許的訊息
        $responseData = $response->json();
        $this->assertStringContainsString('GET method is not supported', $responseData['message']);
    }

    /**
     * 測試缺少action_type參數
     */
    public function test_missing_action_type_parameter(): void
    {
        $response = $this->postJson('/api', [
            'data' => ['message' => 'hello'],
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'VALIDATION_ERROR',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'details' => [
                    'action_type',
                ],
                'timestamp',
            ]);
    }

    /**
     * 測試無效的action_type格式
     */
    public function test_invalid_action_type_format(): void
    {
        $invalidActionTypes = [
            'invalid action', // 包含空格
            'action@type', // 包含特殊字元
            '', // 空字串
            str_repeat('a', 101), // 超過100字元
        ];

        foreach ($invalidActionTypes as $invalidActionType) {
            $response = $this->postJson('/api', [
                'action_type' => $invalidActionType,
            ], [
                'Authorization' => 'Bearer ' . $this->validToken,
            ]);

            $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ]);
        }
    }

    /**
     * 測試不存在的Action
     */
    public function test_non_existent_action(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'non.existent.action',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'ACTION_NOT_FOUND',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp',
            ]);
    }

    /**
     * 測試所有允許的Action類型
     */
    public function test_all_allowed_action_types(): void
    {
        $allowedActions = [
            'test.ping',
            'user.info',
            'user.update',
            'system.status',
        ];

        foreach ($allowedActions as $actionType) {
            $response = $this->postJson('/api', [
                'action_type' => $actionType,
                'data' => ['test' => 'data'],
            ], [
                'Authorization' => 'Bearer ' . $this->validToken,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'action_type' => $actionType,
                    ],
                ]);
        }
    }

    /**
     * 測試回應格式的一致性
     */
    public function test_response_format_consistency(): void
    {
        // 測試成功回應格式
        $successResponse = $this->postJson('/api', [
            'action_type' => 'test.ping',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $successResponse->assertJsonStructure([
            'status',
            'message',
            'data',
            'timestamp',
        ]);

        $successData = $successResponse->json();
        $this->assertEquals('success', $successData['status']);
        $this->assertIsString($successData['message']);
        $this->assertIsArray($successData['data']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $successData['timestamp']);

        // 測試錯誤回應格式
        $errorResponse = $this->postJson('/api', [
            'action_type' => 'non.existent',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $errorResponse->assertJsonStructure([
            'status',
            'message',
            'error_code',
            'timestamp',
        ]);

        $errorData = $errorResponse->json();
        $this->assertEquals('error', $errorData['status']);
        $this->assertIsString($errorData['message']);
        $this->assertIsString($errorData['error_code']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $errorData['timestamp']);
    }

    /**
     * 測試大量資料處理
     */
    public function test_large_data_handling(): void
    {
        $largeData = array_fill(0, 1000, [
            'id' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->email(),
            'data' => fake()->text(200),
        ]);

        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
            'data' => $largeData,
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'action_type' => 'test.ping',
                ],
            ]);
    }

    /**
     * 測試併發請求處理
     */
    public function test_concurrent_requests(): void
    {
        $responses = [];
        
        // 模擬併發請求
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'test.ping',
                'data' => ['request_id' => $i],
            ], [
                'Authorization' => 'Bearer ' . $this->validToken,
            ]);
        }

        // 驗證所有請求都成功
        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ]);
        }
    }

    /**
     * 測試特殊字元處理
     */
    public function test_special_characters_handling(): void
    {
        $specialData = [
            'chinese' => '這是中文測試資料',
            'emoji' => '🚀 測試 emoji 處理',
            'json' => '{"nested": "json data"}',
            'html' => '<script>alert("test")</script>',
            'sql' => "'; DROP TABLE users; --",
        ];

        $response = $this->postJson('/api', [
            'action_type' => 'test.ping',
            'data' => $specialData,
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}