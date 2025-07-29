<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ApiToken;
use App\Models\User;
use Carbon\Carbon;

/**
 * API Token 模型單元測試
 */
class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試建立 Token
     */
    public function test_can_create_token(): void
    {
        $user = User::factory()->create();
        
        $result = ApiToken::createToken(
            userId: $user->id,
            name: '測試 Token',
            permissions: ['api:read', 'api:write']
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertInstanceOf(ApiToken::class, $result['model']);
        $this->assertEquals($user->id, $result['model']->user_id);
        $this->assertEquals('測試 Token', $result['model']->name);
        $this->assertEquals(['api:read', 'api:write'], $result['model']->permissions);
        $this->assertTrue($result['model']->is_active);
    }

    /**
     * 測試 Token 驗證
     */
    public function test_can_validate_token(): void
    {
        $user = User::factory()->create();
        $result = ApiToken::createToken($user->id, '測試 Token');
        
        $apiToken = $result['model'];
        $plainToken = $result['token'];

        $this->assertTrue($apiToken->validateToken($plainToken));
        $this->assertFalse($apiToken->validateToken('invalid-token'));
    }

    /**
     * 測試 Token 過期檢查
     */
    public function test_can_check_token_expiration(): void
    {
        $user = User::factory()->create();
        
        // 測試未過期的 Token
        $validResult = ApiToken::createToken(
            userId: $user->id,
            name: '有效 Token',
            expiresAt: Carbon::now()->addDays(1)
        );
        $this->assertFalse($validResult['model']->isExpired());

        // 測試已過期的 Token
        $expiredResult = ApiToken::createToken(
            userId: $user->id,
            name: '過期 Token',
            expiresAt: Carbon::now()->subDays(1)
        );
        $this->assertTrue($expiredResult['model']->isExpired());

        // 測試永不過期的 Token
        $neverExpiresResult = ApiToken::createToken(
            userId: $user->id,
            name: '永不過期 Token'
        );
        $this->assertFalse($neverExpiresResult['model']->isExpired());
    }

    /**
     * 測試 Token 有效性檢查
     */
    public function test_can_check_token_validity(): void
    {
        $user = User::factory()->create();
        
        // 有效的 Token
        $validResult = ApiToken::createToken($user->id, '有效 Token');
        $this->assertTrue($validResult['model']->isValid());

        // 已停用的 Token
        $inactiveToken = ApiToken::factory()->inactive()->create();
        $this->assertFalse($inactiveToken->isValid());

        // 已過期的 Token
        $expiredToken = ApiToken::factory()->expired()->create();
        $this->assertFalse($expiredToken->isValid());
    }

    /**
     * 測試更新最後使用時間
     */
    public function test_can_update_last_used(): void
    {
        $token = ApiToken::factory()->create(['last_used_at' => null]);
        
        $this->assertNull($token->last_used_at);
        
        $token->updateLastUsed();
        $token->refresh();
        
        $this->assertNotNull($token->last_used_at);
        $this->assertTrue($token->last_used_at->isToday());
    }

    /**
     * 測試撤銷 Token
     */
    public function test_can_revoke_token(): void
    {
        $token = ApiToken::factory()->create();
        
        $this->assertTrue($token->is_active);
        
        $token->revoke();
        $token->refresh();
        
        $this->assertFalse($token->is_active);
        $this->assertFalse($token->isValid());
    }

    /**
     * 測試權限檢查
     */
    public function test_can_check_permissions(): void
    {
        // 測試特定權限
        $token = ApiToken::factory()->withPermissions(['api:read', 'user:create'])->create();
        
        $this->assertTrue($token->hasPermission('api:read'));
        $this->assertTrue($token->hasPermission('user:create'));
        $this->assertFalse($token->hasPermission('admin:delete'));

        // 測試完整權限
        $fullPermissionToken = ApiToken::factory()->withFullPermissions()->create();
        
        $this->assertTrue($fullPermissionToken->hasPermission('api:read'));
        $this->assertTrue($fullPermissionToken->hasPermission('admin:delete'));
        $this->assertTrue($fullPermissionToken->hasPermission('any:permission'));

        // 測試空權限
        $noPermissionToken = ApiToken::factory()->withPermissions([])->create();
        
        $this->assertFalse($noPermissionToken->hasPermission('api:read'));
    }

    /**
     * 測試根據 Token 字串查找模型
     */
    public function test_can_find_by_token(): void
    {
        $user = User::factory()->create();
        $result = ApiToken::createToken($user->id, '測試 Token');
        
        $foundToken = ApiToken::findByToken($result['token']);
        
        $this->assertNotNull($foundToken);
        $this->assertEquals($result['model']->id, $foundToken->id);

        // 測試找不到的情況
        $notFound = ApiToken::findByToken('invalid-token');
        $this->assertNull($notFound);

        // 測試停用的 Token 找不到
        $result['model']->revoke();
        $revokedToken = ApiToken::findByToken($result['token']);
        $this->assertNull($revokedToken);
    }

    /**
     * 測試清理過期 Token
     */
    public function test_can_cleanup_expired_tokens(): void
    {
        // 建立一些 Token
        ApiToken::factory()->count(2)->create(); // 有效的
        ApiToken::factory()->count(3)->expired()->create(); // 過期的
        ApiToken::factory()->inactive()->create(); // 已停用的

        $this->assertEquals(5, ApiToken::where('is_active', true)->count());
        
        $cleanedCount = ApiToken::cleanupExpiredTokens();
        
        $this->assertEquals(3, $cleanedCount);
        $this->assertEquals(2, ApiToken::where('is_active', true)->count());
    }

    /**
     * 測試與使用者的關聯
     */
    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $token->user);
        $this->assertEquals($user->id, $token->user->id);
    }

    /**
     * 測試使用者可以有多個 Token
     */
    public function test_user_can_have_multiple_tokens(): void
    {
        $user = User::factory()->create();
        ApiToken::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertEquals(3, $user->apiTokens()->count());
    }
}