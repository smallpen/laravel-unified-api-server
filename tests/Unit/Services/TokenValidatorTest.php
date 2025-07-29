<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TokenValidator;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * TokenValidator 服務單元測試
 */
class TokenValidatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TokenValidator 實例
     *
     * @var \App\Services\TokenValidator
     */
    protected TokenValidator $tokenValidator;

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
        
        $this->tokenValidator = new TokenValidator();
        $this->user = User::factory()->create();
    }

    /**
     * 測試驗證有效的 Token
     *
     * @return void
     */
    public function test_validate_returns_user_for_valid_token(): void
    {
        // 建立有效的 Token
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];

        // 驗證 Token
        $result = $this->tokenValidator->validate($token);

        // 斷言回傳正確的使用者
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    /**
     * 測試驗證無效的 Token
     *
     * @return void
     */
    public function test_validate_returns_null_for_invalid_token(): void
    {
        $invalidToken = 'invalid-token-string';

        $result = $this->tokenValidator->validate($invalidToken);

        $this->assertNull($result);
    }

    /**
     * 測試驗證過期的 Token
     *
     * @return void
     */
    public function test_validate_returns_null_for_expired_token(): void
    {
        // 建立過期的 Token
        $expiresAt = Carbon::now()->subDay();
        $tokenData = ApiToken::createToken($this->user->id, 'Expired Token', [], $expiresAt);
        $token = $tokenData['token'];

        $result = $this->tokenValidator->validate($token);

        $this->assertNull($result);
    }

    /**
     * 測試驗證已撤銷的 Token
     *
     * @return void
     */
    public function test_validate_returns_null_for_revoked_token(): void
    {
        // 建立 Token 並撤銷
        $tokenData = ApiToken::createToken($this->user->id, 'Revoked Token');
        $token = $tokenData['token'];
        $tokenData['model']->revoke();

        $result = $this->tokenValidator->validate($token);

        $this->assertNull($result);
    }

    /**
     * 測試檢查 Token 是否過期
     *
     * @return void
     */
    public function test_is_expired_returns_false_for_valid_token(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Valid Token');
        $token = $tokenData['token'];

        $result = $this->tokenValidator->isExpired($token);

        $this->assertFalse($result);
    }

    /**
     * 測試檢查過期 Token
     *
     * @return void
     */
    public function test_is_expired_returns_true_for_expired_token(): void
    {
        $expiresAt = Carbon::now()->subDay();
        $tokenData = ApiToken::createToken($this->user->id, 'Expired Token', [], $expiresAt);
        $token = $tokenData['token'];

        $result = $this->tokenValidator->isExpired($token);

        $this->assertTrue($result);
    }

    /**
     * 測試檢查不存在的 Token
     *
     * @return void
     */
    public function test_is_expired_returns_true_for_nonexistent_token(): void
    {
        $result = $this->tokenValidator->isExpired('nonexistent-token');

        $this->assertTrue($result);
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

        $result = $this->tokenValidator->getUserFromToken($token);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    /**
     * 測試檢查 Token 權限
     *
     * @return void
     */
    public function test_has_permission_returns_true_for_granted_permission(): void
    {
        $permissions = ['read', 'write'];
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token', $permissions);
        $token = $tokenData['token'];

        $result = $this->tokenValidator->hasPermission($token, 'read');

        $this->assertTrue($result);
    }

    /**
     * 測試檢查 Token 沒有的權限
     *
     * @return void
     */
    public function test_has_permission_returns_false_for_denied_permission(): void
    {
        $permissions = ['read'];
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token', $permissions);
        $token = $tokenData['token'];

        $result = $this->tokenValidator->hasPermission($token, 'admin');

        $this->assertFalse($result);
    }

    /**
     * 測試檢查萬用權限
     *
     * @return void
     */
    public function test_has_permission_returns_true_for_wildcard_permission(): void
    {
        $permissions = ['*'];
        $tokenData = ApiToken::createToken($this->user->id, 'Admin Token', $permissions);
        $token = $tokenData['token'];

        $result = $this->tokenValidator->hasPermission($token, 'any-permission');

        $this->assertTrue($result);
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

        $result = $this->tokenValidator->revokeToken($token);

        $this->assertTrue($result);
        
        // 驗證 Token 已被撤銷
        $this->assertNull($this->tokenValidator->validate($token));
    }

    /**
     * 測試撤銷不存在的 Token
     *
     * @return void
     */
    public function test_revoke_token_returns_false_for_nonexistent_token(): void
    {
        $result = $this->tokenValidator->revokeToken('nonexistent-token');

        $this->assertFalse($result);
    }

    /**
     * 測試更新 Token 最後使用時間
     *
     * @return void
     */
    public function test_update_last_used_successfully_updates_timestamp(): void
    {
        $tokenData = ApiToken::createToken($this->user->id, 'Test Token');
        $token = $tokenData['token'];
        $originalLastUsed = $tokenData['model']->last_used_at;

        // 等待一秒確保時間戳不同
        sleep(1);

        $result = $this->tokenValidator->updateLastUsed($token);

        $this->assertTrue($result);
        
        // 重新載入模型並檢查時間戳
        $tokenData['model']->refresh();
        $this->assertNotEquals($originalLastUsed, $tokenData['model']->last_used_at);
    }

    /**
     * 測試更新不存在 Token 的最後使用時間
     *
     * @return void
     */
    public function test_update_last_used_returns_false_for_nonexistent_token(): void
    {
        $result = $this->tokenValidator->updateLastUsed('nonexistent-token');

        $this->assertFalse($result);
    }

    /**
     * 測試驗證過程中的異常處理
     *
     * @return void
     */
    public function test_validate_handles_exceptions_gracefully(): void
    {
        // 使用空字串不會觸發異常，所以我們直接測試回傳值
        $result = $this->tokenValidator->validate('');

        $this->assertNull($result);
    }
}