<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TokenService;
use App\Services\TokenValidator;
use App\Services\TokenManager;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * TokenService 統一服務單元測試
 */
class TokenServiceTest extends TestCase
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
        
        $validator = new TokenValidator();
        $manager = new TokenManager();
        $this->tokenService = new TokenService($validator, $manager);
        $this->user = User::factory()->create();
    }

    /**
     * 測試驗證 Token
     *
     * @return void
     */
    public function test_validate_token_returns_user_for_valid_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenService->validateToken($token);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    /**
     * 測試檢查 Token 過期狀態
     *
     * @return void
     */
    public function test_is_token_expired_returns_correct_status(): void
    {
        // 有效 Token
        $validTokenData = ApiToken::createToken($this->user->id, 'Valid Token');
        $validToken = $validTokenData['token'];

        // 過期 Token
        $expiredTokenData = ApiToken::createToken($this->user->id, 'Expired Token', [], Carbon::now()->subDay());
        $expiredToken = $expiredTokenData['token'];

        $this->assertFalse($this->tokenService->isTokenExpired($validToken));
        $this->assertTrue($this->tokenService->isTokenExpired($expiredToken));
    }

    /**
     * 測試從 Token 取得使用者
     *
     * @return void
     */
    public function test_get_user_from_token_returns_correct_user(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenService->getUserFromToken($token);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    /**
     * 測試檢查 Token 權限
     *
     * @return void
     */
    public function test_has_permission_checks_token_permissions(): void
    {
        $permissions = ['read', 'write'];
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token', $permissions);
        $token = $tokenData['token'];

        $this->assertTrue($this->tokenService->hasPermission($token, 'read'));
        $this->assertTrue($this->tokenService->hasPermission($token, 'write'));
        $this->assertFalse($this->tokenService->hasPermission($token, 'admin'));
    }

    /**
     * 測試建立 Token
     *
     * @return void
     */
    public function test_create_token_creates_new_token(): void
    {
        $name = 'New Token';
        $permissions = ['read'];
        $expiresAt = Carbon::now()->addDays(30);

        $result = $this->tokenService->createToken($this->user, $name, $permissions, $expiresAt);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertEquals($name, $result['model']->name);
        $this->assertEquals($permissions, $result['model']->permissions);
    }

    /**
     * 測試撤銷 Token
     *
     * @return void
     */
    public function test_revoke_token_revokes_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenService->revokeToken($token);

        $this->assertTrue($result);
        $this->assertNull($this->tokenService->validateToken($token));
    }

    /**
     * 測試撤銷使用者所有 Token
     *
     * @return void
     */
    public function test_revoke_all_user_tokens_revokes_all_tokens(): void
    {
        // 建立多個 Token
        ApiToken::createToken($this->user->id, 'Token 1');
        ApiToken::createToken($this->user->id, 'Token 2');

        $result = $this->tokenService->revokeAllUserTokens($this->user);

        $this->assertEquals(2, $result);
        $this->assertCount(0, $this->tokenService->getUserTokens($this->user));
    }

    /**
     * 測試建立管理員 Token
     *
     * @return void
     */
    public function test_create_admin_token_creates_token_with_all_permissions(): void
    {
        $result = $this->tokenService->createAdminToken($this->user);

        $this->assertIsArray($result);
        $this->assertEquals(['*'], $result['model']->permissions);
        $this->assertEquals('Admin Token', $result['model']->name);
    }

    /**
     * 測試建立具有過期時間的 Token
     *
     * @return void
     */
    public function test_create_token_with_expiry_sets_correct_expiration(): void
    {
        $daysToExpire = 15;
        $result = $this->tokenService->createTokenWithExpiry($this->user, 'Expiring Token', ['read'], $daysToExpire);

        $expectedExpiry = Carbon::now()->addDays($daysToExpire);
        $actualExpiry = $result['model']->expires_at;

        $this->assertInstanceOf(Carbon::class, $actualExpiry);
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $actualExpiry->format('Y-m-d'));
    }

    /**
     * 測試檢查 Token 是否即將過期
     *
     * @return void
     */
    public function test_is_token_expiring_soon_detects_soon_to_expire_tokens(): void
    {
        // 即將過期的 Token（5天後）
        $soonToExpireData = ApiToken::createToken($this->user->id, 'Soon to Expire', [], Carbon::now()->addDays(5));
        $soonToExpireToken = $soonToExpireData['token'];

        // 不會很快過期的 Token（30天後）
        $notSoonData = ApiToken::createToken($this->user->id, 'Not Soon', [], Carbon::now()->addDays(30));
        $notSoonToken = $notSoonData['token'];

        // 永不過期的 Token
        $neverExpireData = ApiToken::createToken($this->user->id, 'Never Expire');
        $neverExpireToken = $neverExpireData['token'];

        $this->assertTrue($this->tokenService->isTokenExpiringSoon($soonToExpireToken));
        $this->assertFalse($this->tokenService->isTokenExpiringSoon($notSoonToken));
        $this->assertFalse($this->tokenService->isTokenExpiringSoon($neverExpireToken));
    }

    /**
     * 測試取得 Token 剩餘天數
     *
     * @return void
     */
    public function test_get_token_remaining_days_returns_correct_days(): void
    {
        // 10天後過期的 Token（使用startOfDay確保精確計算）
        $expiryDate = Carbon::now()->addDays(10)->startOfDay();
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token', [], $expiryDate);
        $token = $tokenData['token'];

        // 永不過期的 Token
        $neverExpireData = ApiToken::createToken($this->user->id, 'Never Expire');
        $neverExpireToken = $neverExpireData['token'];

        // 已過期的 Token
        $expiredData = ApiToken::createToken($this->user->id, 'Expired', [], Carbon::now()->subDay());
        $expiredToken = $expiredData['token'];

        $remainingDays = $this->tokenService->getTokenRemainingDays($token);
        $this->assertGreaterThanOrEqual(9, $remainingDays);
        $this->assertLessThanOrEqual(10, $remainingDays);
        $this->assertNull($this->tokenService->getTokenRemainingDays($neverExpireToken));
        $this->assertEquals(0, $this->tokenService->getTokenRemainingDays($expiredToken));
    }

    /**
     * 測試更新 Token 最後使用時間
     *
     * @return void
     */
    public function test_update_token_last_used_updates_timestamp(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenService->updateTokenLastUsed($token);

        $this->assertTrue($result);
    }

    /**
     * 測試清理過期 Token
     *
     * @return void
     */
    public function test_cleanup_expired_tokens_removes_expired_tokens(): void
    {
        // 建立過期的 Token
        ApiToken::createToken($this->user->id, 'Expired 1', [], Carbon::now()->subDay());
        ApiToken::createToken($this->user->id, 'Expired 2', [], Carbon::now()->subHour());
        ApiToken::createToken($this->user->id, 'Valid', [], Carbon::now()->addDay());

        $result = $this->tokenService->cleanupExpiredTokens();

        $this->assertEquals(2, $result);
    }

    /**
     * 測試檢查 Token 有效性
     *
     * @return void
     */
    public function test_is_token_valid_checks_token_validity(): void
    {
        $validTokenData = ApiToken::createToken($this->user->id, 'Valid Token');
        $validToken = $validTokenData['token'];

        $expiredTokenData = ApiToken::createToken($this->user->id, 'Expired Token', [], Carbon::now()->subDay());
        $expiredToken = $expiredTokenData['token'];

        $this->assertTrue($this->tokenService->isTokenValid($validToken));
        $this->assertFalse($this->tokenService->isTokenValid($expiredToken));
        $this->assertFalse($this->tokenService->isTokenValid('invalid-token'));
    }

    /**
     * 測試取得 Token 資訊
     *
     * @return void
     */
    public function test_get_token_info_returns_token_details(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenService->getTokenInfo($token);

        $this->assertInstanceOf(ApiToken::class, $result);
        $this->assertEquals($tokenData['model']->id, $result->id);
    }
}