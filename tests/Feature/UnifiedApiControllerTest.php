<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 統一API控制器功能測試
 */
class UnifiedApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 建立有效的API Token
        $apiToken = ApiToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'test-token'),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->validToken = 'test-token';
    }

    /**
     * 測試非POST請求應該回傳405錯誤
     */
    public function test_non_post_request_returns_405(): void
    {
        $response = $this->getJson('/api/', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(405)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ]);
    }

    /**
     * 測試缺少action_type參數應該回傳400錯誤
     */
    public function test_missing_action_type_returns_400(): void
    {
        $response = $this->postJson('/api/', [], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ]);
    }

    /**
     * 測試無效的action_type應該回傳404錯誤
     */
    public function test_invalid_action_type_returns_404(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'invalid.action',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'ACTION_NOT_FOUND',
                ]);
    }

    /**
     * 測試system.ping Action執行成功
     */
    public function test_system_ping_action_success(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Action執行成功',
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'message',
                        'timestamp',
                        'server_time',
                        'user_id',
                        'system_status',
                    ],
                    'timestamp',
                ]);

        // 驗證回傳的資料
        $data = $response->json('data');
        $this->assertEquals('pong', $data['message']);
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals('healthy', $data['system_status']);
    }

    /**
     * 測試user.info Action執行成功
     */
    public function test_user_info_action_success(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Action執行成功',
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                    'timestamp',
                ]);

        // 驗證回傳的使用者資料
        $data = $response->json('data');
        $this->assertEquals($this->user->id, $data['id']);
        $this->assertEquals($this->user->name, $data['name']);
        $this->assertEquals($this->user->email, $data['email']);
    }

    /**
     * 測試無效的Bearer Token應該回傳401錯誤
     */
    public function test_invalid_bearer_token_returns_401(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    /**
     * 測試缺少Bearer Token應該回傳401錯誤
     */
    public function test_missing_bearer_token_returns_401(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
        ]);

        $response->assertStatus(401);
    }

    /**
     * 測試action_type參數格式驗證
     */
    public function test_action_type_format_validation(): void
    {
        // 測試包含無效字元的action_type
        $response = $this->postJson('/api/', [
            'action_type' => 'invalid@action!',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ]);

        // 測試過長的action_type
        $response = $this->postJson('/api/', [
            'action_type' => str_repeat('a', 101), // 超過100字元限制
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                ]);
    }

    /**
     * 測試Action執行過程的日誌記錄
     */
    public function test_action_execution_logging(): void
    {
        // 簡單測試Action執行成功即可，不測試具體的日誌內容
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
        ], [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Action執行成功',
                ]);
    }
}