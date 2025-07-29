<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Services\TokenService;

/**
 * 統一API整合測試
 * 
 * 測試完整的API請求流程，包括Bearer Token驗證
 */
class UnifiedApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $validToken;

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
    }

    /**
     * 測試沒有Bearer Token的請求被拒絕
     */
    public function test_request_without_bearer_token_is_rejected(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED',
                ]);
    }

    /**
     * 測試無效Bearer Token的請求被拒絕
     */
    public function test_request_with_invalid_bearer_token_is_rejected(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => 'Bearer invalid_token_here'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED',
                ]);
    }

    /**
     * 測試有效Bearer Token的完整API請求流程
     */
    public function test_complete_api_request_flow_with_valid_token(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'API請求已成功路由',
                    'data' => [
                        'action_type' => 'test.ping',
                        'user_id' => $this->user->id,
                    ]
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'action_type',
                        'user_id',
                        'timestamp',
                    ],
                    'timestamp'
                ]);
    }

    /**
     * 測試不同的有效Action類型
     */
    public function test_different_valid_action_types(): void
    {
        $validActions = [
            'test.ping',
            'user.info',
            'user.update',
            'system.status',
        ];

        foreach ($validActions as $actionType) {
            $response = $this->postJson('/api', [
                'action_type' => $actionType
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success',
                        'data' => [
                            'action_type' => $actionType,
                            'user_id' => $this->user->id,
                        ]
                    ]);
        }
    }

    /**
     * 測試無效的Action類型回傳404
     */
    public function test_invalid_action_type_returns_404(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'invalid.action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'ACTION_NOT_FOUND',
                ]);
    }

    /**
     * 測試缺少action_type參數的驗證
     */
    public function test_missing_action_type_validation(): void
    {
        $response = $this->postJson('/api', [], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ])
                ->assertJsonValidationErrors(['action_type']);
    }

    /**
     * 測試action_type參數格式驗證
     */
    public function test_action_type_format_validation(): void
    {
        // 測試包含非法字元
        $response = $this->postJson('/api', [
            'action_type' => 'test@action'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ])
                ->assertJsonValidationErrors(['action_type']);

        // 測試過長的字串
        $response = $this->postJson('/api', [
            'action_type' => str_repeat('a', 101)
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['action_type']);
    }

    /**
     * 測試非POST請求方法被拒絕
     */
    public function test_non_post_methods_are_rejected(): void
    {
        $methods = ['GET', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $response = $this->json($method, '/api', [
                'action_type' => 'test.ping'
            ], [
                'Authorization' => "Bearer {$this->validToken}"
            ]);

            $response->assertStatus(405)
                    ->assertJson([
                        'status' => 'error',
                        'error_code' => 'METHOD_NOT_ALLOWED',
                    ]);
        }
    }

    /**
     * 測試Token最後使用時間更新
     */
    public function test_token_last_used_time_is_updated(): void
    {
        // 取得Token的初始最後使用時間
        $tokenModel = ApiToken::where('token_hash', hash('sha256', $this->validToken))->first();
        $initialLastUsed = $tokenModel->last_used_at;

        // 等待一秒確保時間差異
        sleep(1);

        // 發送API請求
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);

        // 檢查Token的最後使用時間是否已更新
        $tokenModel->refresh();
        $this->assertNotEquals($initialLastUsed, $tokenModel->last_used_at);
        $this->assertTrue($tokenModel->last_used_at > $initialLastUsed);
    }

    /**
     * 測試回應時間戳格式
     */
    public function test_response_timestamp_format(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        
        // 檢查主要時間戳格式
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $data['timestamp']
        );

        // 檢查資料中的時間戳格式
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $data['data']['timestamp']
        );
    }

    /**
     * 測試使用者資訊正確傳遞到Action
     */
    public function test_user_information_passed_to_action(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$this->validToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'user_id' => $this->user->id,
                    ]
                ]);
    }

    /**
     * 測試過期Token被拒絕
     */
    public function test_expired_token_is_rejected(): void
    {
        // 建立已過期的Token
        $tokenService = app(TokenService::class);
        $expiredTokenData = $tokenService->createToken(
            $this->user, 
            '過期Token', 
            [], 
            now()->subDay() // 昨天過期
        );

        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => "Bearer {$expiredTokenData['token']}"
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED',
                ]);
    }
}