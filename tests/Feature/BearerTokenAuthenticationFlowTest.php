<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ApiToken;
use App\Services\TokenService;
use App\Http\Middleware\BearerTokenMiddleware;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Bearer Token驗證流程整合測試
 * 
 * 專門測試Bearer Token的完整驗證流程，包括：
 * - Token建立和驗證
 * - Token過期處理
 * - Token權限檢查
 * - Token使用記錄
 * - 中介軟體整合
 */
class BearerTokenAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;
    private TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => '一般使用者',
            'email' => 'user@example.com',
        ]);

        $this->adminUser = User::factory()->create([
            'name' => '管理員使用者',
            'email' => 'admin@example.com',
        ]);

        // 取得TokenService實例
        $this->tokenService = app(TokenService::class);
    }

    /**
     * 測試Token建立流程
     */
    public function test_token_creation_flow(): void
    {
        // 建立基本Token
        $tokenData = $this->tokenService->createToken($this->user, '測試Token');

        // 驗證回傳資料結構
        $this->assertIsArray($tokenData);
        $this->assertArrayHasKey('token', $tokenData);
        $this->assertArrayHasKey('model', $tokenData);

        // 驗證Token格式
        $token = $tokenData['token'];
        $this->assertIsString($token);
        $this->assertGreaterThan(40, strlen($token)); // Token應該足夠長

        // 驗證資料庫記錄
        $tokenModel = $tokenData['model'];
        $this->assertInstanceOf(ApiToken::class, $tokenModel);
        $this->assertEquals($this->user->id, $tokenModel->user_id);
        $this->assertEquals('測試Token', $tokenModel->name);
        $this->assertEquals(hash('sha256', $token), $tokenModel->token_hash);
        $this->assertNotNull($tokenModel->expires_at);
        $this->assertNull($tokenModel->last_used_at);

        // 驗證Token在資料庫中存在
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $this->user->id,
            'name' => '測試Token',
            'token_hash' => hash('sha256', $token)
        ]);
    }

    /**
     * 測試Token建立時的權限設定
     */
    public function test_token_creation_with_permissions(): void
    {
        $permissions = ['user.read', 'user.write', 'admin.read'];
        
        $tokenData = $this->tokenService->createToken(
            $this->user, 
            '權限Token', 
            $permissions
        );

        $tokenModel = $tokenData['model'];
        $this->assertEquals($permissions, $tokenModel->permissions);

        // 驗證資料庫中的權限資料
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $this->user->id,
            'name' => '權限Token',
            'permissions' => json_encode($permissions)
        ]);
    }

    /**
     * 測試Token建立時的過期時間設定
     */
    public function test_token_creation_with_custom_expiry(): void
    {
        $customExpiry = now()->addDays(7);
        
        $tokenData = $this->tokenService->createToken(
            $this->user, 
            '自訂過期Token', 
            [], 
            $customExpiry
        );

        $tokenModel = $tokenData['model'];
        $this->assertEquals(
            $customExpiry->format('Y-m-d H:i:s'), 
            $tokenModel->expires_at->format('Y-m-d H:i:s')
        );
    }

    /**
     * 測試Token驗證流程
     */
    public function test_token_validation_flow(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '驗證測試Token');
        $token = $tokenData['token'];

        // 測試有效Token驗證
        $validatedUser = $this->tokenService->validateToken($token);
        $this->assertNotNull($validatedUser);
        $this->assertEquals($this->user->id, $validatedUser->id);
        $this->assertEquals($this->user->email, $validatedUser->email);

        // 測試無效Token驗證
        $invalidUser = $this->tokenService->validateToken('invalid_token_12345');
        $this->assertNull($invalidUser);

        // 測試空Token驗證
        $emptyUser = $this->tokenService->validateToken('');
        $this->assertNull($emptyUser);

        // 測試null Token驗證
        $nullUser = $this->tokenService->validateToken(null);
        $this->assertNull($nullUser);
    }

    /**
     * 測試Token過期驗證
     */
    public function test_token_expiry_validation(): void
    {
        // 建立已過期的Token
        $expiredTokenData = $this->tokenService->createToken(
            $this->user, 
            '過期Token', 
            [], 
            now()->subDay()
        );
        $expiredToken = $expiredTokenData['token'];

        // 測試過期Token驗證失敗
        $validatedUser = $this->tokenService->validateToken($expiredToken);
        $this->assertNull($validatedUser);

        // 建立即將過期的Token（1分鐘後過期）
        $soonExpiredTokenData = $this->tokenService->createToken(
            $this->user, 
            '即將過期Token', 
            [], 
            now()->addMinute()
        );
        $soonExpiredToken = $soonExpiredTokenData['token'];

        // 測試即將過期但仍有效的Token
        $validatedUser = $this->tokenService->validateToken($soonExpiredToken);
        $this->assertNotNull($validatedUser);
        $this->assertEquals($this->user->id, $validatedUser->id);
    }

    /**
     * 測試Token使用記錄更新
     */
    public function test_token_usage_tracking(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '使用追蹤Token');
        $token = $tokenData['token'];
        $tokenModel = $tokenData['model'];

        // 驗證初始狀態
        $this->assertNull($tokenModel->last_used_at);

        // 第一次使用Token
        $validatedUser = $this->tokenService->validateToken($token);
        $this->assertNotNull($validatedUser);

        // 檢查使用時間是否更新
        $tokenModel->refresh();
        $this->assertNotNull($tokenModel->last_used_at);
        $firstUsedAt = $tokenModel->last_used_at;

        // 等待一秒後再次使用
        sleep(1);
        $validatedUser = $this->tokenService->validateToken($token);
        $this->assertNotNull($validatedUser);

        // 檢查使用時間是否再次更新
        $tokenModel->refresh();
        $this->assertTrue($tokenModel->last_used_at > $firstUsedAt);
    }

    /**
     * 測試Bearer Token中介軟體整合
     */
    public function test_bearer_token_middleware_integration(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '中介軟體測試Token');
        $token = $tokenData['token'];

        // 測試有效Token通過中介軟體
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => "Bearer {$token}"
        ]);

        $response->assertStatus(200);

        // 測試無效Token被中介軟體攔截
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer invalid_token'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試缺少Authorization標頭
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);

        // 測試錯誤的Authorization格式
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Basic ' . base64_encode('user:pass')
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'UNAUTHORIZED'
                ]);
    }

    /**
     * 測試Token撤銷功能
     */
    public function test_token_revocation(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '撤銷測試Token');
        $token = $tokenData['token'];
        $tokenModel = $tokenData['model'];

        // 驗證Token有效
        $validatedUser = $this->tokenService->validateToken($token);
        $this->assertNotNull($validatedUser);

        // 撤銷Token
        $this->tokenService->revokeToken($token);

        // 驗證Token已被撤銷
        $validatedUser = $this->tokenService->validateToken($token);
        $this->assertNull($validatedUser);

        // 驗證資料庫中Token已被刪除或標記為無效
        $this->assertDatabaseMissing('api_tokens', [
            'id' => $tokenModel->id,
            'token_hash' => $tokenModel->token_hash
        ]);
    }

    /**
     * 測試使用者所有Token撤銷
     */
    public function test_revoke_all_user_tokens(): void
    {
        // 為使用者建立多個Token
        $token1Data = $this->tokenService->createToken($this->user, 'Token 1');
        $token2Data = $this->tokenService->createToken($this->user, 'Token 2');
        $token3Data = $this->tokenService->createToken($this->user, 'Token 3');

        $token1 = $token1Data['token'];
        $token2 = $token2Data['token'];
        $token3 = $token3Data['token'];

        // 驗證所有Token都有效
        $this->assertNotNull($this->tokenService->validateToken($token1));
        $this->assertNotNull($this->tokenService->validateToken($token2));
        $this->assertNotNull($this->tokenService->validateToken($token3));

        // 撤銷使用者的所有Token
        $this->tokenService->revokeAllUserTokens($this->user);

        // 驗證所有Token都已無效
        $this->assertNull($this->tokenService->validateToken($token1));
        $this->assertNull($this->tokenService->validateToken($token2));
        $this->assertNull($this->tokenService->validateToken($token3));

        // 驗證資料庫中該使用者的Token都已被刪除
        $this->assertDatabaseMissing('api_tokens', [
            'user_id' => $this->user->id
        ]);
    }

    /**
     * 測試Token權限檢查
     */
    public function test_token_permission_checking(): void
    {
        // 建立具有特定權限的Token
        $limitedPermissions = ['user.read'];
        $limitedTokenData = $this->tokenService->createToken(
            $this->user, 
            '受限權限Token', 
            $limitedPermissions
        );
        $limitedToken = $limitedTokenData['token'];

        // 建立具有所有權限的Token
        $fullPermissions = ['*'];
        $fullTokenData = $this->tokenService->createToken(
            $this->user, 
            '完整權限Token', 
            $fullPermissions
        );
        $fullToken = $fullTokenData['token'];

        // 測試權限檢查功能
        $this->assertTrue(
            $this->tokenService->hasPermission($limitedToken, 'user.read')
        );
        $this->assertFalse(
            $this->tokenService->hasPermission($limitedToken, 'user.write')
        );
        $this->assertFalse(
            $this->tokenService->hasPermission($limitedToken, 'admin.read')
        );

        // 測試完整權限Token
        $this->assertTrue(
            $this->tokenService->hasPermission($fullToken, 'user.read')
        );
        $this->assertTrue(
            $this->tokenService->hasPermission($fullToken, 'user.write')
        );
        $this->assertTrue(
            $this->tokenService->hasPermission($fullToken, 'admin.read')
        );
    }

    /**
     * 測試Token安全性
     */
    public function test_token_security(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '安全測試Token');
        $token = $tokenData['token'];
        $tokenModel = $tokenData['model'];

        // 驗證Token不會以明文儲存
        $this->assertNotEquals($token, $tokenModel->token_hash);
        $this->assertEquals(hash('sha256', $token), $tokenModel->token_hash);

        // 驗證Token具有足夠的隨機性
        $token2Data = $this->tokenService->createToken($this->user, '安全測試Token2');
        $token2 = $token2Data['token'];
        
        $this->assertNotEquals($token, $token2);
        $this->assertNotEquals($tokenModel->token_hash, $token2Data['model']->token_hash);

        // 驗證Token長度足夠
        $this->assertGreaterThanOrEqual(40, strlen($token));
    }

    /**
     * 測試Token在API請求中的完整流程
     */
    public function test_token_complete_api_request_flow(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, 'API流程測試Token');
        $token = $tokenData['token'];
        $tokenModel = $tokenData['model'];

        // 記錄初始狀態
        $initialLastUsed = $tokenModel->last_used_at;

        // 發送API請求
        $response = $this->postJson('/api', [
            'action_type' => 'system.ping',
            'message' => 'Token流程測試'
        ], [
            'Authorization' => "Bearer {$token}"
        ]);

        // 驗證請求成功
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'action_type' => 'system.ping',
                        'user_id' => $this->user->id
                    ]
                ]);

        // 驗證Token使用時間已更新
        $tokenModel->refresh();
        $this->assertNotEquals($initialLastUsed, $tokenModel->last_used_at);
        $this->assertNotNull($tokenModel->last_used_at);

        // 驗證使用者資訊正確傳遞
        $responseData = $response->json();
        $this->assertEquals($this->user->id, $responseData['data']['user_id']);
    }

    /**
     * 測試多個使用者Token的隔離性
     */
    public function test_multi_user_token_isolation(): void
    {
        // 為兩個不同使用者建立Token
        $user1TokenData = $this->tokenService->createToken($this->user, 'User1 Token');
        $user2TokenData = $this->tokenService->createToken($this->adminUser, 'User2 Token');

        $user1Token = $user1TokenData['token'];
        $user2Token = $user2TokenData['token'];

        // 使用User1的Token發送請求
        $response1 = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$user1Token}"
        ]);

        $response1->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'user_id' => $this->user->id
                     ]
                 ]);

        // 使用User2的Token發送請求
        $response2 = $this->postJson('/api', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => "Bearer {$user2Token}"
        ]);

        $response2->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'user_id' => $this->adminUser->id
                     ]
                 ]);

        // 驗證Token不能跨使用者使用
        $this->assertNotEquals(
            $response1->json('data.user_id'),
            $response2->json('data.user_id')
        );
    }

    /**
     * 測試Token的併發使用
     */
    public function test_token_concurrent_usage(): void
    {
        // 建立Token
        $tokenData = $this->tokenService->createToken($this->user, '併發測試Token');
        $token = $tokenData['token'];

        $responses = [];
        $concurrentRequests = 5;

        // 同時發送多個請求使用同一個Token
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->postJson('/api', [
                'action_type' => 'system.ping',
                'concurrent_id' => $i
            ], [
                'Authorization' => "Bearer {$token}"
            ]);
        }

        // 驗證所有請求都成功
        foreach ($responses as $response) {
            $response->assertStatus(200)
                    ->assertJson([
                        'status' => 'success',
                        'data' => [
                            'user_id' => $this->user->id
                        ]
                    ]);
        }

        // 驗證Token的最後使用時間已更新
        $tokenModel = $tokenData['model'];
        $tokenModel->refresh();
        $this->assertNotNull($tokenModel->last_used_at);
    }

    /**
     * 測試Token清理機制
     */
    public function test_token_cleanup_mechanism(): void
    {
        // 建立多個過期Token
        $expiredTokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokenData = $this->tokenService->createToken(
                $this->user, 
                "過期Token {$i}", 
                [], 
                now()->subDays($i + 1)
            );
            $expiredTokens[] = $tokenData;
        }

        // 建立一個有效Token
        $validTokenData = $this->tokenService->createToken($this->user, '有效Token');

        // 執行Token清理
        $cleanedCount = $this->tokenService->cleanupExpiredTokens();

        // 驗證過期Token被清理
        $this->assertEquals(3, $cleanedCount);

        // 驗證有效Token仍然存在
        $validUser = $this->tokenService->validateToken($validTokenData['token']);
        $this->assertNotNull($validUser);

        // 驗證過期Token已無效
        foreach ($expiredTokens as $expiredTokenData) {
            $invalidUser = $this->tokenService->validateToken($expiredTokenData['token']);
            $this->assertNull($invalidUser);
        }
    }
}