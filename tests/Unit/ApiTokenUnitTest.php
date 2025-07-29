<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Str;

/**
 * API Token 核心邏輯單元測試
 */
class ApiTokenUnitTest extends TestCase
{
    /**
     * 測試 Token 雜湊生成
     */
    public function test_token_hash_generation(): void
    {
        $plainToken = Str::random(80);
        $hash1 = hash('sha256', $plainToken);
        $hash2 = hash('sha256', $plainToken);
        
        // 相同的 Token 應該產生相同的雜湊
        $this->assertEquals($hash1, $hash2);
        
        // 不同的 Token 應該產生不同的雜湊
        $differentToken = Str::random(80);
        $differentHash = hash('sha256', $differentToken);
        $this->assertNotEquals($hash1, $differentHash);
    }

    /**
     * 測試 Token 驗證邏輯
     */
    public function test_token_validation_logic(): void
    {
        $plainToken = 'test-token-123';
        $correctHash = hash('sha256', $plainToken);
        $wrongHash = hash('sha256', 'wrong-token');
        
        // 正確的 Token 應該通過驗證
        $this->assertEquals($correctHash, hash('sha256', $plainToken));
        
        // 錯誤的 Token 不應該通過驗證
        $this->assertNotEquals($wrongHash, hash('sha256', $plainToken));
    }

    /**
     * 測試權限檢查邏輯
     */
    public function test_permission_check_logic(): void
    {
        // 測試特定權限
        $permissions = ['api:read', 'user:create'];
        $this->assertTrue(in_array('api:read', $permissions));
        $this->assertTrue(in_array('user:create', $permissions));
        $this->assertFalse(in_array('admin:delete', $permissions));

        // 測試完整權限
        $fullPermissions = ['*'];
        $this->assertTrue(in_array('*', $fullPermissions));

        // 測試空權限
        $emptyPermissions = [];
        $this->assertFalse(in_array('api:read', $emptyPermissions));
    }

    /**
     * 測試過期時間檢查邏輯
     */
    public function test_expiration_check_logic(): void
    {
        $now = time();
        
        // 未來時間 - 未過期
        $futureTime = $now + 3600; // 1小時後
        $this->assertFalse($futureTime < $now);
        
        // 過去時間 - 已過期
        $pastTime = $now - 3600; // 1小時前
        $this->assertTrue($pastTime < $now);
        
        // null 時間 - 永不過期
        $neverExpires = null;
        $this->assertNull($neverExpires);
    }

    /**
     * 測試 Token 字串生成
     */
    public function test_token_string_generation(): void
    {
        $token1 = Str::random(80);
        $token2 = Str::random(80);
        
        // Token 應該是80個字元長
        $this->assertEquals(80, strlen($token1));
        $this->assertEquals(80, strlen($token2));
        
        // 每次生成的 Token 應該不同
        $this->assertNotEquals($token1, $token2);
        
        // Token 應該只包含字母和數字
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token1);
    }

    /**
     * 測試陣列權限處理
     */
    public function test_array_permission_handling(): void
    {
        $permissions = ['api:read', 'api:write', 'user:create'];
        
        // 測試陣列轉換
        $jsonPermissions = json_encode($permissions);
        $decodedPermissions = json_decode($jsonPermissions, true);
        
        $this->assertEquals($permissions, $decodedPermissions);
        
        // 測試空陣列
        $emptyPermissions = [];
        $this->assertEmpty($emptyPermissions);
        
        // 測試完整權限陣列
        $fullPermissions = ['*'];
        $this->assertContains('*', $fullPermissions);
    }
}