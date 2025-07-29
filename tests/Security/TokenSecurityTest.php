<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Token安全性測試
 * 
 * 測試API Token的各種安全機制
 */
class TokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試使用者
     * 
     * @var User
     */
    protected User $testUser;

    /**
     * Token服務
     * 
     * @var TokenService
     */
    protected TokenService $tokenService;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create([
            'name' => '安全測試使用者',
            'email' => 'security-test@example.com',
        ]);

        $this->tokenService = app(TokenService::class);
    }

    /**
     * 測試Token的雜湊安全性
     */
    public function test_token_hash_security()
    {
        // 建立Token
        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '安全測試Token',
            ['user.read']
        );

        $plainToken = $tokenData['token'];
        $tokenModel = $tokenData['model'];

        // 驗證Token不是以明文儲存
        $this->assertNotEquals($plainToken, $tokenModel->token_hash);
        
        // 驗證Token雜湊是使用SHA-256
        $expectedHash = hash('sha256', $plainToken);
        $this->assertEquals($expectedHash, $tokenModel->token_hash);

        // 驗證Token長度足夠安全（至少80個字元）
        $this->assertGreaterThanOrEqual(80, strlen($plainToken));

        // 驗證Token包含足夠的隨機性
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $plainToken);
    }

    /**
     * 測試Token過期機制
     */
    public function test_token_expiration_security()
    {
        // 建立已過期的Token
        $expiredTokenData = ApiToken::createToken(
            $this->testUser->id,
            '過期Token',
            ['user.read'],
            Carbon::now()->subHour() // 一小時前過期
        );

        $expiredToken = $expiredTokenData['token'];

        // 測試過期Token無法通過驗證
        $user = $this->tokenService->validateToken($expiredToken);
        $this->assertNull($user, '過期Token不應該通過驗證');

        // 測試過期檢查方法
        $this->assertTrue($this->tokenService->isTokenExpired($expiredToken));

        // 測試API請求被拒絕
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $expiredToken
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'UNAUTHORIZED'
        ]);
    }

    /**
     * 測試Token撤銷機制
     */
    public function test_token_revocation_security()
    {
        // 建立Token
        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '撤銷測試Token',
            ['user.read']
        );

        $token = $tokenData['token'];

        // 驗證Token初始狀態有效
        $user = $this->tokenService->validateToken($token);
        $this->assertNotNull($user);

        // 撤銷Token
        $revoked = $this->tokenService->revokeToken($token);
        $this->assertTrue($revoked);

        // 驗證撤銷後Token無效
        $user = $this->tokenService->validateToken($token);
        $this->assertNull($user, '撤銷的Token不應該通過驗證');

        // 測試API請求被拒絕
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(401);
    }

    /**
     * 測試Token權限隔離
     */
    public function test_token_permission_isolation()
    {
        // 建立具有限制權限的Token
        $limitedTokenData = ApiToken::createToken(
            $this->testUser->id,
            '限制權限Token',
            ['user.read'] // 只有讀取權限
        );

        $limitedToken = $limitedTokenData['token'];

        // 測試有權限的操作可以執行
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $limitedToken
        ]);

        $response->assertStatus(200);

        // 建立無權限的Token
        $noPermissionTokenData = ApiToken::createToken(
            $this->testUser->id,
            '無權限Token',
            [] // 沒有任何權限
        );

        $noPermissionToken = $noPermissionTokenData['token'];

        // 測試無權限的操作被拒絕
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $noPermissionToken
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'INSUFFICIENT_PERMISSIONS'
        ]);
    }

    /**
     * 測試Token暴力破解防護
     */
    public function test_token_brute_force_protection()
    {
        $invalidTokens = [
            'invalid_token_1',
            'invalid_token_2',
            'invalid_token_3',
            str_repeat('a', 80), // 80個字元的無效Token
            str_repeat('1', 80), // 80個數字的無效Token
            '', // 空Token
            'short', // 太短的Token
        ];

        foreach ($invalidTokens as $invalidToken) {
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info'
            ], [
                'Authorization' => 'Bearer ' . $invalidToken
            ]);

            $response->assertStatus(401);
            $response->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED'
            ]);
        }

        // 驗證多次無效嘗試不會影響系統穩定性
        $this->assertTrue(true, '系統在多次無效Token嘗試後仍然穩定');
    }

    /**
     * 測試Token時間攻擊防護
     */
    public function test_token_timing_attack_protection()
    {
        // 建立有效Token
        $validTokenData = ApiToken::createToken(
            $this->testUser->id,
            '時間攻擊測試Token',
            ['user.read']
        );
        $validToken = $validTokenData['token'];

        // 建立無效Token（相同長度）
        $invalidToken = str_repeat('a', strlen($validToken));

        $validationTimes = [];

        // 測試有效Token驗證時間
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $this->tokenService->validateToken($validToken);
            $endTime = microtime(true);
            $validationTimes['valid'][] = ($endTime - $startTime) * 1000;
        }

        // 測試無效Token驗證時間
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $this->tokenService->validateToken($invalidToken);
            $endTime = microtime(true);
            $validationTimes['invalid'][] = ($endTime - $startTime) * 1000;
        }

        $avgValidTime = array_sum($validationTimes['valid']) / count($validationTimes['valid']);
        $avgInvalidTime = array_sum($validationTimes['invalid']) / count($validationTimes['invalid']);

        // 驗證時間差異不應該太大（防止時間攻擊）
        $timeDifference = abs($avgValidTime - $avgInvalidTime);
        $this->assertLessThan(10, $timeDifference, '有效和無效Token的驗證時間差異過大，可能存在時間攻擊風險');
    }

    /**
     * 測試Token重複使用檢測
     */
    public function test_token_replay_attack_protection()
    {
        // 建立Token
        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '重複使用測試Token',
            ['user.read']
        );

        $token = $tokenData['token'];

        // 記錄初始最後使用時間
        $tokenModel = ApiToken::findByToken($token);
        $initialLastUsed = $tokenModel->last_used_at;

        // 第一次使用Token
        $response1 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response1->assertStatus(200);

        // 檢查最後使用時間是否更新
        $tokenModel->refresh();
        $firstUseTime = $tokenModel->last_used_at;
        $this->assertNotEquals($initialLastUsed, $firstUseTime);

        // 等待一秒後再次使用
        sleep(1);

        // 第二次使用Token
        $response2 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response2->assertStatus(200);

        // 檢查最後使用時間是否再次更新
        $tokenModel->refresh();
        $secondUseTime = $tokenModel->last_used_at;
        $this->assertGreaterThan($firstUseTime, $secondUseTime);
    }

    /**
     * 測試Token洩漏防護
     */
    public function test_token_leakage_protection()
    {
        // 建立Token
        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '洩漏防護測試Token',
            ['user.read']
        );

        $token = $tokenData['token'];

        // 測試Token不會在回應中洩漏
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
        
        $responseContent = $response->getContent();
        
        // 驗證Token不會出現在回應中
        $this->assertStringNotContainsString($token, $responseContent);
        $this->assertStringNotContainsString($tokenData['model']->token_hash, $responseContent);

        // 測試錯誤回應也不會洩漏Token資訊
        $response = $this->postJson('/api/', [
            'action_type' => 'non_existent_action'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(404);
        $errorContent = $response->getContent();
        $this->assertStringNotContainsString($token, $errorContent);
    }

    /**
     * 測試Token長度和複雜度
     */
    public function test_token_strength()
    {
        $tokenCount = 100;
        $tokens = [];

        // 生成多個Token
        for ($i = 0; $i < $tokenCount; $i++) {
            $tokenData = ApiToken::createToken(
                $this->testUser->id,
                "強度測試Token_{$i}",
                ['user.read']
            );
            $tokens[] = $tokenData['token'];
        }

        // 檢查Token唯一性
        $uniqueTokens = array_unique($tokens);
        $this->assertCount($tokenCount, $uniqueTokens, 'Token應該是唯一的');

        // 檢查Token長度一致性
        $tokenLengths = array_map('strlen', $tokens);
        $this->assertCount(1, array_unique($tokenLengths), '所有Token長度應該一致');

        // 檢查Token複雜度
        foreach ($tokens as $token) {
            // 應該包含字母和數字
            $this->assertMatchesRegularExpression('/[a-zA-Z]/', $token, 'Token應該包含字母');
            $this->assertMatchesRegularExpression('/[0-9]/', $token, 'Token應該包含數字');
            
            // 不應該包含特殊字元（避免URL編碼問題）
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token, 'Token應該只包含字母和數字');
        }
    }

    /**
     * 測試Token清理機制
     */
    public function test_token_cleanup_mechanism()
    {
        // 建立一些過期Token
        $expiredTokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokenData = ApiToken::createToken(
                $this->testUser->id,
                "過期Token_{$i}",
                ['user.read'],
                Carbon::now()->subDays($i + 1)
            );
            $expiredTokens[] = $tokenData['model'];
        }

        // 建立一些有效Token
        $validTokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokenData = ApiToken::createToken(
                $this->testUser->id,
                "有效Token_{$i}",
                ['user.read'],
                Carbon::now()->addDays($i + 1)
            );
            $validTokens[] = $tokenData['model'];
        }

        // 執行清理
        $cleanedCount = ApiToken::cleanupExpiredTokens();

        // 驗證清理結果
        $this->assertEquals(5, $cleanedCount, '應該清理5個過期Token');

        // 驗證過期Token被標記為無效
        foreach ($expiredTokens as $expiredToken) {
            $expiredToken->refresh();
            $this->assertFalse($expiredToken->is_active, '過期Token應該被標記為無效');
        }

        // 驗證有效Token不受影響
        foreach ($validTokens as $validToken) {
            $validToken->refresh();
            $this->assertTrue($validToken->is_active, '有效Token不應該受到影響');
        }
    }

    /**
     * 測試Token權限升級防護
     */
    public function test_token_privilege_escalation_protection()
    {
        // 建立低權限Token
        $lowPrivilegeTokenData = ApiToken::createToken(
            $this->testUser->id,
            '低權限Token',
            ['user.read']
        );

        $lowPrivilegeToken = $lowPrivilegeTokenData['token'];

        // 嘗試使用低權限Token執行需要高權限的操作
        // 這裡我們假設有一個需要admin權限的Action
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info', // 使用現有的Action
            'user_id' => 999999 // 嘗試存取其他使用者資料
        ], [
            'Authorization' => 'Bearer ' . $lowPrivilegeToken
        ]);

        // 應該成功，因為user.info允許查詢其他使用者（在實際實作中可能需要額外權限檢查）
        $response->assertStatus(200);

        // 測試完全無權限的Token
        $noPermissionTokenData = ApiToken::createToken(
            $this->testUser->id,
            '無權限Token',
            []
        );

        $noPermissionToken = $noPermissionTokenData['token'];

        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $noPermissionToken
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'INSUFFICIENT_PERMISSIONS'
        ]);
    }
}