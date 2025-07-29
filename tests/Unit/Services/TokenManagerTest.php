<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TokenManager;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * TokenManager 服務單元測試
 */
class TokenManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TokenManager 實例
     *
     * @var \App\Services\TokenManager
     */
    protected TokenManager $tokenManager;

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
        
        $this->tokenManager = new TokenManager();
        $this->user = User::factory()->create();
    }

    /**
     * 測試建立 Token
     *
     * @return void
     */
    public function test_create_token_successfully_creates_token(): void
    {
        $name = 'Test Token';
        $permissions = ['read', 'write'];
        $expiresAt = Carbon::now()->addDays(30);

        $result = $this->tokenManager->createToken($this->user, $name, $permissions, $expiresAt);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertInstanceOf(ApiToken::class, $result['model']);
        $this->assertEquals($name, $result['model']->name);
        $this->assertEquals($permissions, $result['model']->permissions);
        $this->assertEquals($this->user->id, $result['model']->user_id);
    }

    /**
     * 測試建立沒有過期時間的 Token
     *
     * @return void
     */
    public function test_create_token_without_expiry(): void
    {
        $result = $this->tokenManager->createToken($this->user, 'Permanent Token');

        $this->assertNull($result['model']->expires_at);
    }

    /**
     * 測試撤銷 Token
     *
     * @return void
     */
    public function test_revoke_token_successfully_revokes_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenManager->revokeToken($token);

        $this->assertTrue($result);
        
        // 驗證 Token 已被撤銷
        $tokenData['model']->refresh();
        $this->assertFalse($tokenData['model']->is_active);
    }

    /**
     * 測試撤銷不存在的 Token
     *
     * @return void
     */
    public function test_revoke_token_returns_false_for_nonexistent_token(): void
    {
        $result = $this->tokenManager->revokeToken('nonexistent-token');

        $this->assertFalse($result);
    }

    /**
     * 測試撤銷使用者的所有 Token
     *
     * @return void
     */
    public function test_revoke_all_user_tokens_revokes_all_tokens(): void
    {
        // 建立多個 Token
        ApiToken::createToken($this->user->id, 'Token 1');
        ApiToken::createToken($this->user->id, 'Token 2');
        ApiToken::createToken($this->user->id, 'Token 3');

        $result = $this->tokenManager->revokeAllUserTokens($this->user);

        $this->assertEquals(3, $result);
        
        // 驗證所有 Token 都被撤銷
        $activeTokens = ApiToken::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(0, $activeTokens);
    }

    /**
     * 測試撤銷指定名稱的 Token
     *
     * @return void
     */
    public function test_revoke_tokens_by_name_revokes_matching_tokens(): void
    {
        // 建立不同名稱的 Token
        ApiToken::createToken($this->user->id, 'Mobile App');
        ApiToken::createToken($this->user->id, 'Mobile App');
        ApiToken::createToken($this->user->id, 'Web App');

        $result = $this->tokenManager->revokeTokensByName($this->user, 'Mobile App');

        $this->assertEquals(2, $result);
        
        // 驗證只有指定名稱的 Token 被撤銷
        $mobileTokens = ApiToken::where('user_id', $this->user->id)
            ->where('name', 'Mobile App')
            ->where('is_active', true)
            ->count();
        $webTokens = ApiToken::where('user_id', $this->user->id)
            ->where('name', 'Web App')
            ->where('is_active', true)
            ->count();
            
        $this->assertEquals(0, $mobileTokens);
        $this->assertEquals(1, $webTokens);
    }

    /**
     * 測試取得使用者的 Token
     *
     * @return void
     */
    public function test_get_user_tokens_returns_active_tokens(): void
    {
        // 建立 Token
        $token1 = ApiToken::createToken($this->user->id, 'Token 1');
        $token2 = ApiToken::createToken($this->user->id, 'Token 2');
        $token3 = ApiToken::createToken($this->user->id, 'Token 3');
        
        // 撤銷一個 Token
        $token2['model']->revoke();

        $result = $this->tokenManager->getUserTokens($this->user);

        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('id', $token1['model']->id));
        $this->assertFalse($result->contains('id', $token2['model']->id));
        $this->assertTrue($result->contains('id', $token3['model']->id));
    }

    /**
     * 測試清理過期的 Token
     *
     * @return void
     */
    public function test_cleanup_expired_tokens_removes_expired_tokens(): void
    {
        // 建立有效和過期的 Token
        ApiToken::createToken($this->user->id, 'Valid Token');
        ApiToken::createToken($this->user->id, 'Expired Token 1', [], Carbon::now()->subDay());
        ApiToken::createToken($this->user->id, 'Expired Token 2', [], Carbon::now()->subHour());

        $result = $this->tokenManager->cleanupExpiredTokens();

        $this->assertEquals(2, $result);
        
        // 驗證過期 Token 被標記為非活躍
        $activeTokens = ApiToken::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(1, $activeTokens);
    }

    /**
     * 測試檢查 Token 有效性
     *
     * @return void
     */
    public function test_is_token_valid_returns_true_for_valid_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Valid Token');
        $token = $tokenData['token'];

        $result = $this->tokenManager->isTokenValid($token);

        $this->assertTrue($result);
    }

    /**
     * 測試檢查無效 Token
     *
     * @return void
     */
    public function test_is_token_valid_returns_false_for_invalid_token(): void
    {
        $result = $this->tokenManager->isTokenValid('invalid-token');

        $this->assertFalse($result);
    }

    /**
     * 測試檢查過期 Token
     *
     * @return void
     */
    public function test_is_token_valid_returns_false_for_expired_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Expired Token', [], Carbon::now()->subDay());
        $token = $tokenData['token'];

        $result = $this->tokenManager->isTokenValid($token);

        $this->assertFalse($result);
    }

    /**
     * 測試取得 Token 資訊
     *
     * @return void
     */
    public function test_get_token_info_returns_token_model(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        $result = $this->tokenManager->getTokenInfo($token);

        $this->assertInstanceOf(ApiToken::class, $result);
        $this->assertEquals($tokenData['model']->id, $result->id);
    }

    /**
     * 測試取得不存在 Token 的資訊
     *
     * @return void
     */
    public function test_get_token_info_returns_null_for_nonexistent_token(): void
    {
        $result = $this->tokenManager->getTokenInfo('nonexistent-token');

        $this->assertNull($result);
    }

    /**
     * 測試建立 Token 時的異常處理
     *
     * @return void
     */
    public function test_create_token_handles_exceptions(): void
    {
        // 模擬日誌記錄
        Log::shouldReceive('error')->once();

        // 使用無效的使用者 ID 觸發異常
        $invalidUser = new User();
        $invalidUser->id = 99999;

        $this->expectException(\Exception::class);
        $this->tokenManager->createToken($invalidUser, 'Test Token');
    }

    /**
     * 測試撤銷所有使用者 Token 時的異常處理
     *
     * @return void
     */
    public function test_revoke_all_user_tokens_handles_exceptions(): void
    {
        // 模擬日誌記錄
        Log::shouldReceive('error')->once();

        // 使用無效的使用者觸發異常
        $invalidUser = new User();
        $invalidUser->id = null;

        $result = $this->tokenManager->revokeAllUserTokens($invalidUser);

        $this->assertEquals(0, $result);
    }
}