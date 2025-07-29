<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TokenService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Token 服務整合測試
 * 
 * 測試 Token 服務在實際應用環境中的整合功能
 */
class TokenServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TokenService 實例
     *
     * @var \App\Services\TokenService
     */
    protected TokenService $tokenService;

    /**
     * 測試用使用者
     *
     * @var \App\Models\User
     */
    protected User $user;

    /**
     * 設定測試環境
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tokenService = app(TokenService::class);
        $this->user = User::factory()->create();
    }

    /**
     * 測試完整的 Token 生命週期
     *
     * @return void
     */
    public function test_complete_token_lifecycle(): void
    {
        // 1. 建立 Token
        $tokenData = $this->tokenService->createToken(
            $this->user,
            'Integration Test Token',
            ['read', 'write'],
            Carbon::now()->addDays(30)
        );

        $token = $tokenData['token'];
        $this->assertNotEmpty($token);

        // 2. 驗證 Token
        $user = $this->tokenService->validateToken($token);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);

        // 3. 檢查權限
        $this->assertTrue($this->tokenService->hasPermission($token, 'read'));
        $this->assertTrue($this->tokenService->hasPermission($token, 'write'));
        $this->assertFalse($this->tokenService->hasPermission($token, 'admin'));

        // 4. 更新最後使用時間
        $this->assertTrue($this->tokenService->updateTokenLastUsed($token));

        // 5. 檢查 Token 有效性
        $this->assertTrue($this->tokenService->isTokenValid($token));
        $this->assertFalse($this->tokenService->isTokenExpired($token));

        // 6. 撤銷 Token
        $this->assertTrue($this->tokenService->revokeToken($token));

        // 7. 驗證 Token 已被撤銷
        $this->assertNull($this->tokenService->validateToken($token));
        $this->assertFalse($this->tokenService->isTokenValid($token));
    }

    /**
     * 測試多個 Token 的管理
     *
     * @return void
     */
    public function test_multiple_tokens_management(): void
    {
        // 建立多個不同類型的 Token
        $mobileToken = $this->tokenService->createToken($this->user, 'Mobile App', ['read']);
        $webToken = $this->tokenService->createToken($this->user, 'Web App', ['read', 'write']);
        $adminToken = $this->tokenService->createAdminToken($this->user, 'Admin Panel');

        // 驗證所有 Token 都有效
        $this->assertInstanceOf(User::class, $this->tokenService->validateToken($mobileToken['token']));
        $this->assertInstanceOf(User::class, $this->tokenService->validateToken($webToken['token']));
        $this->assertInstanceOf(User::class, $this->tokenService->validateToken($adminToken['token']));

        // 檢查權限差異
        $this->assertTrue($this->tokenService->hasPermission($mobileToken['token'], 'read'));
        $this->assertFalse($this->tokenService->hasPermission($mobileToken['token'], 'write'));

        $this->assertTrue($this->tokenService->hasPermission($webToken['token'], 'read'));
        $this->assertTrue($this->tokenService->hasPermission($webToken['token'], 'write'));

        $this->assertTrue($this->tokenService->hasPermission($adminToken['token'], 'any-permission'));

        // 取得使用者所有 Token
        $userTokens = $this->tokenService->getUserTokens($this->user);
        $this->assertCount(3, $userTokens);

        // 撤銷所有 Token
        $revokedCount = $this->tokenService->revokeAllUserTokens($this->user);
        $this->assertEquals(3, $revokedCount);

        // 驗證所有 Token 都已撤銷
        $this->assertNull($this->tokenService->validateToken($mobileToken['token']));
        $this->assertNull($this->tokenService->validateToken($webToken['token']));
        $this->assertNull($this->tokenService->validateToken($adminToken['token']));
    }

    /**
     * 測試過期 Token 的處理
     *
     * @return void
     */
    public function test_expired_token_handling(): void
    {
        // 建立即將過期的 Token
        $soonToExpireToken = $this->tokenService->createTokenWithExpiry(
            $this->user,
            'Soon to Expire',
            ['read'],
            5 // 5天後過期
        );

        // 建立已過期的 Token
        $expiredTokenData = $this->tokenService->createToken(
            $this->user,
            'Expired Token',
            ['read'],
            Carbon::now()->subDay()
        );

        $soonToken = $soonToExpireToken['token'];
        $expiredToken = $expiredTokenData['token'];

        // 檢查過期狀態
        $this->assertFalse($this->tokenService->isTokenExpired($soonToken));
        $this->assertTrue($this->tokenService->isTokenExpired($expiredToken));

        // 檢查即將過期的 Token
        $this->assertTrue($this->tokenService->isTokenExpiringSoon($soonToken));

        // 檢查剩餘天數
        $remainingDays = $this->tokenService->getTokenRemainingDays($soonToken);
        $this->assertGreaterThanOrEqual(4, $remainingDays);
        $this->assertLessThanOrEqual(5, $remainingDays);

        $this->assertEquals(0, $this->tokenService->getTokenRemainingDays($expiredToken));

        // 過期的 Token 應該無法驗證
        $this->assertNull($this->tokenService->validateToken($expiredToken));
        $this->assertFalse($this->tokenService->isTokenValid($expiredToken));

        // 清理過期 Token
        $cleanedCount = $this->tokenService->cleanupExpiredTokens();
        $this->assertEquals(1, $cleanedCount);
    }

    /**
     * 測試 Token 服務的依賴注入
     *
     * @return void
     */
    public function test_token_service_dependency_injection(): void
    {
        // 從服務容器取得 TokenService
        $tokenService1 = app(TokenService::class);
        $tokenService2 = app(TokenService::class);

        // 驗證是單例模式
        $this->assertSame($tokenService1, $tokenService2);

        // 驗證服務正常運作
        $tokenData = $tokenService1->createToken($this->user, 'DI Test Token');
        $user = $tokenService2->validateToken($tokenData['token']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);
    }

    /**
     * 測試 Token 資訊查詢
     *
     * @return void
     */
    public function test_token_information_retrieval(): void
    {
        $permissions = ['read', 'write', 'delete'];
        $tokenName = 'Information Test Token';
        $expiresAt = Carbon::now()->addDays(15);

        $tokenData = $this->tokenService->createToken(
            $this->user,
            $tokenName,
            $permissions,
            $expiresAt
        );

        $token = $tokenData['token'];

        // 取得 Token 資訊
        $tokenInfo = $this->tokenService->getTokenInfo($token);

        $this->assertNotNull($tokenInfo);
        $this->assertEquals($tokenName, $tokenInfo->name);
        $this->assertEquals($permissions, $tokenInfo->permissions);
        $this->assertEquals($this->user->id, $tokenInfo->user_id);
        $this->assertTrue($tokenInfo->is_active);
        $this->assertEquals($expiresAt->format('Y-m-d H:i:s'), $tokenInfo->expires_at->format('Y-m-d H:i:s'));

        // 檢查使用者關聯
        $this->assertEquals($this->user->id, $tokenInfo->user->id);
    }

    /**
     * 測試按名稱撤銷 Token
     *
     * @return void
     */
    public function test_revoke_tokens_by_name(): void
    {
        // 建立多個同名 Token
        $this->tokenService->createToken($this->user, 'Mobile App', ['read']);
        $this->tokenService->createToken($this->user, 'Mobile App', ['read']);
        $this->tokenService->createToken($this->user, 'Web App', ['read', 'write']);

        // 撤銷指定名稱的 Token
        $revokedCount = $this->tokenService->revokeTokensByName($this->user, 'Mobile App');
        $this->assertEquals(2, $revokedCount);

        // 驗證剩餘的 Token
        $remainingTokens = $this->tokenService->getUserTokens($this->user);
        $this->assertCount(1, $remainingTokens);
        $this->assertEquals('Web App', $remainingTokens->first()->name);
    }
}