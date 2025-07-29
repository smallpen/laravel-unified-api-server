<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;

/**
 * Bearer Token 整合測試
 */
class BearerTokenIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 測試TokenService在測試環境中是否正常運作
     */
    public function test_token_service_works_in_test_environment(): void
    {
        $tokenService = app(\App\Services\TokenService::class);
        $this->assertNotNull($tokenService);
        
        $tokenData = $tokenService->createToken($this->user, '測試Token');
        $this->assertArrayHasKey('token', $tokenData);
        $this->assertArrayHasKey('model', $tokenData);
        
        $validatedUser = $tokenService->validateToken($tokenData['token']);
        $this->assertNotNull($validatedUser);
        $this->assertEquals($this->user->id, $validatedUser->id);
    }

    /**
     * 測試Bearer Token中介軟體是否正確註冊
     */
    public function test_bearer_token_middleware_is_registered(): void
    {
        $middleware = app(\App\Http\Middleware\BearerTokenMiddleware::class);
        $this->assertNotNull($middleware);
    }

    /**
     * 測試API路由是否正確應用Bearer Token中介軟體
     */
    public function test_api_route_applies_bearer_token_middleware(): void
    {
        // 不提供Bearer Token的請求應該被中介軟體攔截
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ]);

        // 檢查是否被中介軟體攔截（不是404路由錯誤）
        $this->assertNotEquals(404, $response->getStatusCode());
        
        // 如果是401，表示中介軟體正常運作
        // 如果是500，表示中介軟體有問題
        if ($response->getStatusCode() === 500) {
            // 輸出錯誤資訊以便除錯
            $content = $response->getContent();
            $this->fail("中介軟體發生500錯誤: " . $content);
        }
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * 測試有效Token的API請求
     */
    public function test_valid_token_api_request(): void
    {
        // 直接建立ApiToken記錄
        $tokenData = ApiToken::createToken($this->user->id, '測試Token');
        $token = $tokenData['token'];
        
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => "Bearer {$token}"
        ]);

        if ($response->getStatusCode() === 500) {
            $content = $response->getContent();
            $this->fail("API請求發生500錯誤: " . $content);
        }
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'action_type' => 'test.ping',
                        'user_id' => $this->user->id,
                    ]
                ]);
    }

    /**
     * 測試無效Token的API請求
     */
    public function test_invalid_token_api_request(): void
    {
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ], [
            'Authorization' => 'Bearer invalid_token_here'
        ]);

        if ($response->getStatusCode() === 500) {
            $content = $response->getContent();
            $this->fail("無效Token請求發生500錯誤: " . $content);
        }
        
        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED',
                ]);
    }
}